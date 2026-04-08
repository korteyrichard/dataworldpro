<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\MoolreSmsService;

class CodeCraftOrderStatusSyncService
{
    private $apiKey = '250905051915-|9zeDO-YdLmuU-rCa?vb-TqFnqX-TcWFy3';
    private $baseUrl = 'https://api.codecraftnetwork.com/api';

    public function syncOrderStatus(Order $order)
    {
        if (!$order->reference_id || $order->status === 'completed') {
            return;
        }

        $isBigTime = $this->isBigTimeOrder($order);
        $endpoint = $isBigTime 
            ? $this->baseUrl . '/response_big_time.php'
            : $this->baseUrl . '/response_regular.php';
            
        try {
            Log::info('Making CodeCraft API status check', [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
                'endpoint' => $endpoint,
                'is_bigtime' => $isBigTime
            ]);
            
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->get($endpoint, ['reference_id' => $order->reference_id]);
                
            Log::info('CodeCraft API status response', [
                'order_id' => $order->id,
                'status_code' => $response->status(),
                'response_body' => $response->body()
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']['order_status'])) {
                    $externalStatus = $data['data']['order_status'];
                    $newStatus = $this->mapStatus($externalStatus);
                    
                    Log::info('CodeCraft status mapping', [
                        'order_id' => $order->id,
                        'external_status' => $externalStatus,
                        'mapped_status' => $newStatus,
                        'current_status' => $order->status
                    ]);
                    
                    if ($newStatus && $newStatus !== $order->status) {
                        $oldStatus = $order->status;
                        $order->update(['status' => $newStatus]);
                        
                        Log::info('CodeCraft order status updated', [
                            'order_id' => $order->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus
                        ]);
                        
                        // Send SMS if completed
                        if ($newStatus === 'completed' && $order->user && $order->user->phone) {
                            $this->sendCompletionSms($order);
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error('CodeCraft status check failed', [
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
            ->where('order_pusher_status', 'success')
            ->get();

        foreach ($pendingOrders as $order) {
            $this->syncOrderStatus($order);
        }
    }

    private function isBigTimeOrder(Order $order)
    {
        foreach ($order->products as $product) {
            if (stripos($product->name, 'bigtime') !== false || stripos($product->name, 'big') !== false) {
                return true;
            }
        }
        return false;
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
        $smsService = new MoolreSmsService();
        $firstProduct = $order->products->first();
        $dataSize = '';
        
        if ($firstProduct && $firstProduct->pivot->product_variant_id) {
            $variant = \App\Models\ProductVariant::find($firstProduct->pivot->product_variant_id);
            if ($variant && isset($variant->variant_attributes['size'])) {
                $dataSize = strtoupper($variant->variant_attributes['size']) . ' ';
            }
        }
        
        $productName = $firstProduct ? $firstProduct->name : 'Data/Airtime';
        $message = "Your order #{$order->id} for {$dataSize}{$productName} to {$order->beneficiary_number} ({$order->network}) has been completed. Total: GHS " . number_format($order->total, 2);
        $smsService->sendSms($order->user->phone, $message);
    }
}