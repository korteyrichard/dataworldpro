<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JescoOrderStatusSyncService
{
    private $baseUrl = 'https://jesscostore.com/api/v1';
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.jesco.api_key', 'jsk_your_api_key_here');
    }

    public function syncOrderStatuses()
    {
        $processingOrders = Order::whereIn('status', ['pending', 'processing'])
            ->where('network', 'MTN')
            ->whereNotNull('reference_id')
            ->where('order_pusher_status', 'success')
            ->get();
        
        Log::info('Jesco sync starting', ['orders_count' => $processingOrders->count()]);
        
        foreach ($processingOrders as $order) {
            try {
                $this->syncJescoOrderStatus($order);
            } catch (\Exception $e) {
                Log::error('Failed to sync Jesco order status', [
                    'orderId' => $order->id, 
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function syncJescoOrderStatus($order)
    {
        // Load order relationships for SMS
        $order->load(['user', 'products' => function($query) {
            $query->withPivot('product_variant_id');
        }]);
        
        // Extract Jesco purchase ID from reference_id
        $jescoReference = $this->extractJescoReference($order);
        
        Log::info('Jesco sync attempt', [
            'order_id' => $order->id,
            'jesco_reference' => $jescoReference,
            'order_network' => $order->network,
            'order_status' => $order->status
        ]);
        
        if (!$jescoReference) {
            Log::warning('No Jesco reference found for order', ['orderId' => $order->id]);
            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(20)->get($this->baseUrl . '/purchases/' . $jescoReference);

            Log::info('Jesco API response received', [
                'order_id' => $order->id,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'is_successful' => $response->successful()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success'] === true && isset($data['data'])) {
                    $purchaseData = $data['data'];
                    $externalStatus = $purchaseData['status'] ?? '';
                    $newStatus = $this->mapJescoStatus($externalStatus);
                    
                    Log::info('Jesco status mapping', [
                        'order_id' => $order->id,
                        'external_status' => $externalStatus,
                        'mapped_status' => $newStatus,
                        'current_order_status' => $order->status
                    ]);
                    
                    if ($newStatus && $newStatus !== $order->status) {
                        $oldStatus = $order->status;
                        $updateResult = $order->update(['status' => $newStatus]);
                        Log::info('Jesco order status updated', [
                            'orderId' => $order->id, 
                            'oldStatus' => $oldStatus, 
                            'newStatus' => $newStatus,
                            'update_successful' => $updateResult
                        ]);
                        
                        // Send SMS if status changed to completed
                        if ($newStatus === 'completed' && $oldStatus !== 'completed' && $order->user && $order->user->phone) {
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
                    } else {
                        Log::info('Jesco order status unchanged', [
                            'order_id' => $order->id,
                            'current_status' => $order->status,
                            'external_status' => $externalStatus,
                            'mapped_status' => $newStatus
                        ]);
                    }
                } else {
                    Log::warning('Jesco API response missing data', [
                        'order_id' => $order->id,
                        'response_data' => $data
                    ]);
                }
            } else {
                Log::warning('Jesco API call unsuccessful', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Jesco status check failed', [
                'orderId' => $order->id, 
                'error' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function extractJescoReference($order)
    {
        // The reference_id should contain the Jesco purchase ID from JescoOrderPusherService
        $purchaseId = $order->reference_id;
        
        // Only return numeric purchase IDs (Jesco purchase IDs are numeric)
        if ($purchaseId && is_numeric($purchaseId)) {
            return $purchaseId;
        }
        
        Log::warning('Invalid Jesco purchase ID format', [
            'order_id' => $order->id,
            'reference_id' => $purchaseId,
            'expected' => 'numeric purchase ID'
        ]);
        
        return null;
    }

    private function mapJescoStatus($externalStatus)
    {
        Log::info('Jesco Status mapping debug', [
            'input_status' => $externalStatus,
            'input_type' => gettype($externalStatus),
            'input_lowercased' => strtolower($externalStatus)
        ]);
        
        $statusMap = [
            'pending' => 'processing',
            'processing' => 'processing', 
            'completed' => 'completed',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled'
        ];

        $lowercaseStatus = strtolower($externalStatus);
        $mappedStatus = $statusMap[$lowercaseStatus] ?? null;
        
        Log::info('Jesco Status mapping result', [
            'original_status' => $externalStatus,
            'lowercase_status' => $lowercaseStatus,
            'mapped_status' => $mappedStatus,
            'available_mappings' => array_keys($statusMap)
        ]);

        return $mappedStatus;
    }
}