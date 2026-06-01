<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Skanka5OrderStatusSyncTelecelService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.skanka5.base_url', 'https://agent.skanka5.com/api/v1');
        $this->apiKey = config('services.skanka5.api_key', '');
    }

    public function syncSingleOrderStatus(Order $order)
    {
        try {
            if (strtolower($order->network) === 'telecel') {
                $this->syncSkanka5TelecelOrderStatus($order);
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync single Skanka5 Telecel order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    public function syncOrderStatuses()
    {
        $processingOrders = Order::with(['user', 'products' => function($query) {
            $query->withPivot('product_variant_id');
        }])
        ->whereIn('status', ['pending', 'processing'])
        ->where('network', 'Telecel')
        ->whereNotNull('reference_id')
        ->get();
        
        foreach ($processingOrders as $order) {
            try {
                $this->syncSkanka5TelecelOrderStatus($order);
            } catch (\Exception $e) {
                Log::error('Failed to sync Skanka5 Telecel order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function syncSkanka5TelecelOrderStatus($order)
    {
        $referenceId = $order->reference_id;
        
        Log::info('Skanka5 Telecel sync attempt', [
            'order_id' => $order->id,
            'reference_id' => $referenceId,
            'order_network' => $order->network,
            'order_status' => $order->status
        ]);
        
        if (!$referenceId) {
            Log::warning('No reference ID found for Skanka5 Telecel order', ['orderId' => $order->id]);
            return;
        }

        try {
            $endpoint = $this->baseUrl . '/orders/' . $referenceId;
            
            Log::info('Making Skanka5 Telecel API call', [
                'order_id' => $order->id,
                'reference_id' => $referenceId,
                'api_endpoint' => $endpoint
            ]);
            
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json'
            ])->timeout(20)->get($endpoint);

            Log::info('Skanka5 Telecel API response received', [
                'order_id' => $order->id,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'is_successful' => $response->successful()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Check if the response has the expected structure
                if (!isset($data['status'])) {
                    Log::warning('Skanka5 Telecel API response missing status field', [
                        'order_id' => $order->id,
                        'response_data' => $data
                    ]);
                    return;
                }
                
                $externalStatus = $data['status'];
                $newStatus = $this->mapSkanka5TelecelStatus($externalStatus);
                
                Log::info('Skanka5 Telecel status mapping', [
                    'order_id' => $order->id,
                    'external_status' => $externalStatus,
                    'mapped_status' => $newStatus,
                    'current_order_status' => $order->status
                ]);
                
                if ($newStatus && $newStatus !== $order->status) {
                    $oldStatus = $order->status;
                    $updateResult = $order->update(['status' => $newStatus]);
                    Log::info('Skanka5 Telecel order status updated', [
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
                    Log::info('Skanka5 Telecel order status unchanged', [
                        'order_id' => $order->id,
                        'current_status' => $order->status,
                        'external_status' => $externalStatus,
                        'mapped_status' => $newStatus
                    ]);
                }
            } else {
                Log::warning('Skanka5 Telecel API call unsuccessful', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Skanka5 Telecel status check failed', [
                'orderId' => $order->id, 
                'error' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function mapSkanka5TelecelStatus($externalStatus)
    {
        Log::info('Skanka5 Telecel Status mapping debug', [
            'input_status' => $externalStatus,
            'input_type' => gettype($externalStatus),
            'input_lowercased' => strtolower($externalStatus)
        ]);
        
        $statusMap = [
            'processed' => 'completed',
            'completed' => 'completed',
            'successful' => 'completed',
            'delivered' => 'completed',
            'processing' => 'processing',
            'pending' => 'processing',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled',
            'rejected' => 'cancelled'
        ];

        $lowercaseStatus = strtolower($externalStatus);
        $mappedStatus = $statusMap[$lowercaseStatus] ?? null;
        
        Log::info('Skanka5 Telecel Status mapping result', [
            'original_status' => $externalStatus,
            'lowercase_status' => $lowercaseStatus,
            'mapped_status' => $mappedStatus,
            'available_mappings' => array_keys($statusMap)
        ]);

        return $mappedStatus;
    }
}