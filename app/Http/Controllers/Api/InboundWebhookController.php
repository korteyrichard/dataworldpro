<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\MoolreSmsService;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InboundWebhookController extends Controller
{
    public function bundleportal(Request $request)
    {
        $secret = config('services.bundleportal.webhook_secret');
        $signature = (string) $request->header('X-BundlePortal-Signature', '');
        $expected = $secret ? 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret) : '';

        if (!$secret || !$signature || !hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();
        $reference = $payload['reference'] ?? null;
        $status = $payload['status'] ?? null;
        if (!$reference || !$status) {
            return response()->json(['message' => 'Missing reference or status'], 422);
        }

        $order = Order::where('bundleportal_reference_id', $reference)
            ->orWhere('reference_id', $reference)
            ->first();
        if (!$order) {
            Log::warning('Bundle Portal webhook order not found', ['reference' => $reference]);
            // A 2xx prevents retries for a reference that is not in this application.
            return response()->json(['message' => 'Order not found'], 200);
        }

        $newStatus = $this->mapStatus((string) $status, 'bundleportal');
        if (!$newStatus || $newStatus === $order->status) {
            return response()->json(['message' => 'Webhook already applied or status ignored'], 200);
        }

        $this->updateOrderStatus((string) $reference, (string) $status, 'bundleportal');
        if ($newStatus === 'completed') {
            (new CommissionService())->makeCommissionsAvailable($order);
        } elseif (in_array($newStatus, ['cancelled', 'refunded'], true)) {
            (new CommissionService())->reverseCommissions($order);
        }
        return response()->json(['message' => 'Webhook processed'], 200);
    }

    public function masternetdatahub(Request $request)
    {
        $secret = config('services.webhooks.masternetdatahub');

        if ($secret && !$this->verifySignature($request, $secret)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        Log::info('MasterNet DataHub webhook received', ['payload' => $request->all()]);

        $referenceId = $request->input('reference_id') ?? $request->input('order.reference_id') ?? $request->input('order_id');
        $externalStatus = $request->input('status') ?? $request->input('order.status');

        if (!$referenceId || !$externalStatus) {
            return response()->json(['message' => 'Missing reference_id or status'], 422);
        }

        $this->updateOrderStatus($referenceId, $externalStatus, 'masternetdatahub');

        return response()->json(['message' => 'Webhook processed'], 200);
    }

    public function jessco(Request $request)
    {
        $secret = config('services.webhooks.jessco');

        if ($secret && !$this->verifySignature($request, $secret)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        Log::info('Jessco webhook received', ['payload' => $request->all()]);

        $referenceId = $request->input('reference_id') ?? $request->input('purchase_id') ?? $request->input('order_id');
        $externalStatus = $request->input('status');

        if (!$referenceId || !$externalStatus) {
            return response()->json(['message' => 'Missing reference_id or status'], 422);
        }

        $this->updateOrderStatus($referenceId, $externalStatus, 'jessco');

        return response()->json(['message' => 'Webhook processed'], 200);
    }

    public function skanka5(Request $request)
    {
        $secret = config('services.webhooks.skanka5');

        if (!$secret || !$this->verifyTskconnectSignature($request, $secret)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $data = $payload['data'] ?? $payload;
        $orderData = $data['order'] ?? [];
        Log::info('Tskconnect webhook received', ['payload' => $payload]);

        $referenceId = $data['externalReference']
            ?? $data['reference']
            ?? $data['reference_id']
            ?? $orderData['externalReference']
            ?? $request->input('reference_id')
            ?? $request->input('order_id');
        $externalStatus = $data['status'] ?? $orderData['status'] ?? $request->input('status');

        if (!$referenceId || !$externalStatus) {
            return response()->json(['message' => 'Missing reference_id or status'], 422);
        }

        $this->updateOrderStatus($referenceId, $externalStatus, 'skanka5');

        return response()->json(['message' => 'Webhook processed'], 200);
    }

    private function verifyTskconnectSignature(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Tskconnect-Signature', '');
        $timestamp = (string) $request->header('X-Tskconnect-Timestamp', '');

        if ($signature === '' || $timestamp === '') {
            return false;
        }

        $signature = preg_replace('/^sha256=/', '', $signature);
        $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    private function updateOrderStatus(string $referenceId, string $externalStatus, string $source): void
    {
        $orderQuery = Order::with(['user', 'products' => function ($query) {
            $query->withPivot('product_variant_id');
        }]);
        if ($source === 'bundleportal') {
            $orderQuery->where(function ($query) use ($referenceId) {
                $query->where('bundleportal_reference_id', $referenceId)
                    ->orWhere('reference_id', $referenceId);
            });
        } else {
            $orderQuery->where('reference_id', $referenceId);
        }
        $order = $orderQuery->first();

        if (!$order) {
            Log::warning("Webhook order not found", ['reference_id' => $referenceId, 'source' => $source]);
            return;
        }

        $newStatus = $this->mapStatus($externalStatus, $source);

        if (!$newStatus || $newStatus === $order->status) {
            Log::info("Webhook status unchanged", ['order_id' => $order->id, 'current' => $order->status, 'external' => $externalStatus, 'source' => $source]);
            return;
        }

        $oldStatus = $order->status;
        $order->update(['status' => $newStatus]);

        Log::info("Webhook order status updated", [
            'order_id' => $order->id,
            'source' => $source,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        // Send SMS on completion
        if ($newStatus === 'completed' && $oldStatus !== 'completed' && $order->user && $order->user->phone) {
            $smsService = new MoolreSmsService();
            $firstProduct = $order->products->first();
            $dataSize = '';
            if ($firstProduct && $firstProduct->pivot->product_variant_id) {
                $variant = ProductVariant::find($firstProduct->pivot->product_variant_id);
                if ($variant && isset($variant->variant_attributes['size'])) {
                    $dataSize = strtoupper($variant->variant_attributes['size']) . ' ';
                }
            }
            $productName = $firstProduct ? $firstProduct->name : 'Data/Airtime';
            $message = "Your order #{$order->id} for {$dataSize}{$productName} to {$order->beneficiary_number} ({$order->network}) has been completed. Total: GHS " . number_format($order->total, 2);
            $smsService->sendSms($order->user->phone, $message);
        }
    }

    private function mapStatus(string $externalStatus, string $source = ''): ?string
    {
        $statusMap = [
            'delivered' => 'completed',
            'completed' => 'completed',
            'successful' => 'completed',
            'processed' => 'completed',
            'processing' => 'processing',
            'pending' => 'processing',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled',
            'rejected' => 'cancelled',
            'refunded' => 'cancelled',
        ];

        if ($source === 'masternetdatahub') {
            unset($statusMap['failed'], $statusMap['cancelled'], $statusMap['rejected']);
        }

        return $statusMap[strtolower($externalStatus)] ?? null;
    }

    private function verifySignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Webhook-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('Authorization');

        if (!$signature) {
            return false;
        }

        $cleanSignature = str_replace('Bearer ', '', $signature);

        if (hash_equals($secret, $cleanSignature)) {
            return true;
        }

        // HMAC fallback
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expected, $cleanSignature);
    }
}
