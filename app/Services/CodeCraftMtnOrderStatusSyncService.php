<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\MoolreSmsService;

class CodeCraftMtnOrderStatusSyncService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.codecraft_mtn.base_url');
        $this->apiKey = config('services.codecraft_mtn.api_key');
    }

    public function syncOrderStatus(Order $order)
    {
        if (!$order->reference_id || $order->status === 'completed') {
            return;
        }

        $endpoint = $this->baseUrl . '/response_regular.php';

        try {
            Log::info('Making CodeCraft MTN API status check', [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
            ]);

            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->get($endpoint, ['reference_id' => $order->reference_id]);

            Log::info('CodeCraft MTN API status response', [
                'order_id' => $order->id,
                'status_code' => $response->status(),
                'response_body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data']['order_status'])) {
                    $externalStatus = $data['data']['order_status'];
                    $newStatus = $this->mapStatus($externalStatus);

                    if ($newStatus && $newStatus !== $order->status) {
                        $oldStatus = $order->status;
                        $order->update(['status' => $newStatus]);

                        Log::info('CodeCraft MTN order status updated', [
                            'order_id' => $order->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus
                        ]);

                        if ($newStatus === 'completed' && $order->user && $order->user->phone) {
                            $this->sendCompletionSms($order);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('CodeCraft MTN status check failed', [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function syncPendingOrders()
    {
        $pendingOrders = Order::where('status', 'pending')
            ->whereNotNull('reference_id')
            ->where('reference_id', 'like', 'API%')
            ->where('order_pusher_status', 'success')
            ->where('network', 'mtn')
            ->get();

        Log::info('CodeCraft MTN status sync started', [
            'pending_orders_found' => $pendingOrders->count()
        ]);

        foreach ($pendingOrders as $order) {
            $this->syncOrderStatus($order);
        }

        Log::info('CodeCraft MTN status sync completed');
    }

    private function mapStatus($externalStatus)
    {
        $statusMap = [
            'completed' => 'completed',
            'successful' => 'completed',
            'delivered' => 'completed',
            'pending' => 'pending',
            'processing' => 'pending',
            'failed' => 'failed',
            'cancelled' => 'failed'
        ];

        return $statusMap[strtolower($externalStatus)] ?? null;
    }

    private function sendCompletionSms(Order $order)
    {
        try {
            $smsService = new MoolreSmsService();
            $firstProduct = $order->products->first();
            $dataSize = '';

            if ($firstProduct && $firstProduct->pivot->product_variant_id) {
                $variant = \App\Models\ProductVariant::find($firstProduct->pivot->product_variant_id);
                if ($variant && isset($variant->variant_attributes['size'])) {
                    $dataSize = strtoupper($variant->variant_attributes['size']) . ' ';
                }
            }

            $productName = $firstProduct ? $firstProduct->name : 'MTN Data';
            $message = "Your order #{$order->id} for {$dataSize}{$productName} to {$order->beneficiary_number} ({$order->network}) has been completed. Total: GHS " . number_format($order->total, 2);
            $smsService->sendSms($order->user->phone, $message);
        } catch (\Exception $e) {
            Log::warning('SMS sending failed for CodeCraft MTN order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
