<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Skanka5OrderPusherService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.skanka5.base_url', 'https://agent.skanka5.com/api/v1');
        $this->apiKey = config('services.skanka5.api_key', '');
    }

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for Skanka5 API push', [
            'order_id' => $order->id,
            'api_key_length' => strlen($this->apiKey),
            'api_key_empty' => empty($this->apiKey),
            'base_url' => $this->baseUrl
        ]);
        
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        Log::info('Order has items', ['count' => $items->count()]);
        
        $processedItems = 0;

        foreach ($items as $item) {
            Log::info('Processing item', ['name' => $item->name]);
            
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            
            // Get size in GB and convert to MB
            $sizeInGB = $variant && isset($variant->variant_attributes['size']) ? (int)filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $volumeMB = $sizeInGB * 1000; // Convert GB to MB
            $networkId = $this->getNetworkIdFromProduct($item->name);
            
            Log::info('Item details', [
                'product' => $item->name,
                'beneficiary' => $beneficiaryPhone,
                'volume_mb' => $volumeMB,
                'network_id' => $networkId
            ]);

            if (empty($beneficiaryPhone)) {
                Log::warning('No beneficiary phone found for item, skipping');
                continue;
            }

            if (!$networkId || !$volumeMB) {
                Log::warning('Skipping non-MTN product or missing data', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'product' => $item->name
                ]);
                continue;
            }
            
            $processedItems++;

            $endpoint = $this->baseUrl . '/orders';
            $payload = [
                'network_id' => $networkId,
                'msisdn' => $this->formatPhone($beneficiaryPhone),
                'volume_mb' => $volumeMB
            ];
            
            Log::info('Sending to Skanka5 API', [
                'endpoint' => $endpoint, 
                'payload' => $payload,
                'api_key_present' => !empty($this->apiKey),
                'api_key_first_10' => substr($this->apiKey, 0, 10) . '...',
                'headers' => [
                    'x-api-key' => substr($this->apiKey, 0, 10) . '...',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);

            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Idempotency-Key' => 'order_' . $order->id . '_' . $item->id . '_' . time()
                ])->timeout(30)->post($endpoint, $payload);

                Log::info('Skanka5 API Response', [
                    'status_code' => $response->status(),
                    'body' => $response->body()
                ]);

                if ($response->status() === 202) {
                    $responseData = $response->json();
                    
                    Log::info('Detailed Skanka5 API response data', [
                        'order_id' => $order->id,
                        'raw_response' => $response->body(),
                        'parsed_response' => $responseData
                    ]);
                    
                    if (isset($responseData['reference'])) {
                        $referenceId = $responseData['reference'];
                        
                        Log::info('About to update order with reference_id', [
                            'order_id' => $order->id,
                            'current_reference_id' => $order->reference_id,
                            'new_reference_id' => $referenceId
                        ]);
                        
                        $updateResult = $order->update([
                            'reference_id' => $referenceId,
                            'order_pusher_status' => 'success'
                        ]);
                        
                        $order->refresh();
                        
                        Log::info('Order update result', [
                            'order_id' => $order->id,
                            'update_successful' => $updateResult,
                            'reference_id_after_update' => $order->reference_id,
                            'order_pusher_status' => $order->order_pusher_status
                        ]);
                    } else {
                        $order->update(['order_pusher_status' => 'failed']);
                        Log::warning('Skanka5 API response missing reference', [
                            'order_id' => $order->id,
                            'response' => $responseData
                        ]);
                    }
                } else {
                    $order->update(['order_pusher_status' => 'failed']);
                    Log::error('Skanka5 API call failed', [
                        'order_id' => $order->id,
                        'status_code' => $response->status(),
                        'response' => $response->body()
                    ]);
                }

            } catch (\Exception $e) {
                $order->update(['order_pusher_status' => 'failed']);
                Log::error('Skanka5 API Error', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        if ($processedItems === 0) {
            Log::info('No items were processed for order, keeping status as disabled', ['order_id' => $order->id]);
        }
    }
    
    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
            return $phone;
        }
        
        return $phone;
    }
    
    private function getNetworkIdFromProduct($productName)
    {
        $productName = strtolower($productName);
        
        if (stripos($productName, 'mtn') !== false) {
            return 3; // MTN network ID based on the documentation
        }
        
        return null;
    }

    public function checkOrderStatus($referenceId)
    {
        $endpoint = $this->baseUrl . '/orders/' . $referenceId;
        
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json'
            ])->timeout(30)->get($endpoint);
            
            $responseData = $response->json();
            
            Log::info('Skanka5 order status check response', [
                'reference_id' => $referenceId,
                'response' => $responseData
            ]);
            
            return $responseData;
            
        } catch (\Exception $e) {
            Log::error('Skanka5 order status check failed', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}