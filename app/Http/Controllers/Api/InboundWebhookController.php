<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\MoolreSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InboundWebhookController extends Controller
{
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

        if ($secret && !$this->verifySignature($request, $secret)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        Log::info('Skanka5 webhook received', ['payload' => $request->all()]);

        $referenceId = $request->input('reference_id') ?? $request->input('order_id');
        $externalStatus = $request->input('status');

        if (!$referenceId || !$externalStatus) {
            return response()->json(['message' => 'Missing reference_id or status'], 422);
        }

        $this->updateOrderStatus($referenceId, $externalStatus, 'skanka5');

        return response()->json(['message' => 'Webhook processed'], 200);
    }

    private function updateOrderStatus(string $referenceId, string $externalStatus, string $source): void
    {
        $order = Order::with(['user', 'products' => function ($query) {
            $query->withPivot('product_variant_id');
        }])->where('reference_id', $referenceId)->first();

        if (!$order) {
            Log::warning("Webhook order not found", ['reference_id' => $referenceId, 'source' => $source]);
            return;
        }

        $newStatus = $this->mapStatus($externalStatus);

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

    private function mapStatus(string $externalStatus): ?string
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
        ];

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
