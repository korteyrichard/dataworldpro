<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JescoOrderPusherService
{
    private $baseUrl = 'https://jesscostore.com/api/v1';
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.jesco.api_key', 'jsk_your_api_key_here');
    }

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing MTN order for Jesco API push', ['order_id' => $order->id]);
        
        // Only process MTN orders
        if (strtolower($order->network) !== 'mtn') {
            Log::info('Skipping non-MTN order for Jesco service', ['order_id' => $order->id, 'network' => $order->network]);
            return;
        }
        
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        Log::info('Order has items', ['count' => $items->count()]);
        
        $processedItems = 0;

        foreach ($items as $item) {
            Log::info('Processing item', ['name' => $item->name]);
            
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            
            if (empty($beneficiaryPhone)) {
                Log::warning('No beneficiary phone found for item, skipping');
                continue;
            }

            if (!$variant) {
                Log::warning('No variant found for item, skipping', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'product' => $item->name
                ]);
                continue;
            }
            
            $processedItems++;

            // Map variant size to Jesco package identifier
            Log::info('Variant details for Jesco mapping', [
                'order_id' => $order->id,
                'variant_id' => $variant->id,
                'variant_attributes' => $variant->variant_attributes,
                'item_name' => $item->name
            ]);
            
            $packageId = $this->getJescoPackageId($variant);
            if (!$packageId) {
                Log::warning('Could not map variant to Jesco package - skipping item', [
                    'order_id' => $order->id,
                    'variant_id' => $variant->id,
                    'variant_attributes' => $variant->variant_attributes,
                    'note' => 'Jesco package mapping needs to be configured with valid package IDs'
                ]);
                // Mark order as disabled since we cannot process it
                $order->update(['order_pusher_status' => 'disabled']);
                continue;
            }
            
            Log::info('Successfully mapped to Jesco package', [
                'order_id' => $order->id,
                'variant_size' => $variant->variant_attributes['size'] ?? 'unknown',
                'jesco_package_id' => $packageId
            ]);

            $payload = [
                'package' => $packageId,
                'phone' => $this->formatPhone($beneficiaryPhone),
                'reference' => 'ORDER_' . $order->id . '_' . $item->id,
                'meta' => [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'customer_id' => $order->user_id
                ]
            ];
            
            Log::info('Sending to Jesco API', ['payload' => $payload]);

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($this->baseUrl . '/purchase', $payload);

                Log::info('Jesco API Response', [
                    'status_code' => $response->status(),
                    'body' => $response->body()
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    Log::info('Detailed Jesco API response data', [
                        'order_id' => $order->id,
                        'raw_response' => $response->body(),
                        'parsed_response' => $responseData
                    ]);
                    
                    if (isset($responseData['success']) && $responseData['success'] === true && isset($responseData['data'])) {
                        $purchaseData = $responseData['data'];
                        $jescoPurchaseId = $purchaseData['id'] ?? null;
                        
                        if ($jescoPurchaseId) {
                            $order->update([
                                'reference_id' => $jescoPurchaseId,
                                'order_pusher_status' => 'success'
                            ]);
                            
                            Log::info('Order updated with Jesco purchase ID', [
                                'order_id' => $order->id,
                                'jesco_purchase_id' => $jescoPurchaseId
                            ]);
                        } else {
                            $order->update(['order_pusher_status' => 'success']);
                            Log::info('Jesco purchase successful but no purchase ID found');
                        }
                    } else {
                        $order->update(['order_pusher_status' => 'failed']);
                        Log::warning('Jesco API response indicates failure', [
                            'order_id' => $order->id,
                            'response' => $responseData
                        ]);
                    }
                } else {
                    $order->update(['order_pusher_status' => 'failed']);
                    
                    $responseBody = $response->body();
                    $statusCode = $response->status();
                    
                    // Check for specific error types
                    if ($statusCode === 401) {
                        Log::error('Jesco API authentication failed - check API key', [
                            'order_id' => $order->id,
                            'status_code' => $statusCode,
                            'api_key_prefix' => substr($this->apiKey, 0, 10) . '...',
                            'response' => $responseBody
                        ]);
                    } elseif ($statusCode === 422) {
                        Log::error('Jesco API validation error - invalid package or data', [
                            'order_id' => $order->id,
                            'status_code' => $statusCode,
                            'package_sent' => $packageId,
                            'phone_sent' => $this->formatPhone($beneficiaryPhone),
                            'response' => $responseBody
                        ]);
                    } else {
                        Log::error('Jesco API call failed', [
                            'order_id' => $order->id,
                            'status_code' => $statusCode,
                            'response' => $responseBody
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $order->update(['order_pusher_status' => 'failed']);
                Log::error('Jesco API Error', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        if ($processedItems === 0) {
            Log::info('No items were processed for Jesco order, keeping status as disabled', ['order_id' => $order->id]);
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

    private function getJescoPackageId($variant)
    {
        if (!isset($variant->variant_attributes['size'])) {
            Log::warning('Variant has no size attribute', ['variant_id' => $variant->id]);
            return null;
        }

        $size = strtolower(trim($variant->variant_attributes['size']));
        Log::info('Looking up Jesco package for size', ['size' => $size, 'original_size' => $variant->variant_attributes['size']]);
        
        // Map variant sizes to actual Jesco package IDs
        $packageMap = [
            '1gb' => 'MTN11-1GB',
            '2gb' => 'MTN11-2GB',
            '3gb' => 'MTN11-3GB',
            '4gb' => 'MTN11-4GB',
            '5gb' => 'MTN11-5GB',
            '6gb' => 'MTN11-6GB',
            '7gb' => 'MTN11-7GB',
            '8gb' => 'MTN11-8GB',
            '9gb' => 'MTN11-9GB',
            '10gb' => 'MTN11-10GB',
            '15gb' => 'MTN11-15GB',
            '20gb' => 'MTN11-20GB',
            '25gb' => 'MTN11-25GB',
            '30gb' => 'MTN11-30GB',
            '40gb' => 'MTN11-40GB',
            '50gb' => 'MTN11-50GB',
            '100gb' => 'MTN11-100GB'
        ];

        $mappedPackage = $packageMap[$size] ?? null;
        
        if ($mappedPackage) {
            Log::info('Successfully mapped to Jesco package', [
                'size' => $size,
                'jesco_package_id' => $mappedPackage
            ]);
        } else {
            Log::warning('No Jesco package mapping found for size', [
                'size' => $size,
                'available_sizes' => array_keys($packageMap)
            ]);
        }
        
        return $mappedPackage;
    }
}