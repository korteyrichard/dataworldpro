<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EasyDataOrderPusherService
{
    private $baseUrl;
    private $username;
    private $password;

    public function __construct()
    {
        $this->baseUrl = config('services.easydata.base_url');
        $this->username = config('services.easydata.username');
        $this->password = config('services.easydata.password');
        
        Log::info('EasyData service initialized', [
            'base_url' => $this->baseUrl,
            'username' => $this->username,
            'password_set' => !empty($this->password)
        ]);
    }

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing MTN order for EasyData API push', ['order_id' => $order->id]);
        
        // Only process MTN orders
        if (strtolower($order->network) !== 'mtn') {
            Log::info('Skipping non-MTN order for EasyData service', ['order_id' => $order->id, 'network' => $order->network]);
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

            $packageSize = $this->getPackageSize($variant);
            if (!$packageSize) {
                Log::warning('Could not determine package size - skipping item', [
                    'order_id' => $order->id,
                    'variant_id' => $variant->id,
                    'variant_attributes' => $variant->variant_attributes
                ]);
                $order->update(['order_pusher_status' => 'disabled']);
                continue;
            }
            
            Log::info('Successfully mapped to EasyData package', [
                'order_id' => $order->id,
                'variant_size' => $variant->variant_attributes['size'] ?? 'unknown',
                'package_size' => $packageSize
            ]);

            $payload = [
                'network' => 'mtn',
                'recipient' => $this->formatPhone($beneficiaryPhone),
                'package_size' => $packageSize,
                'order_id' => 'ORDER_' . $order->id . '_' . $item->id
            ];
            
            Log::info('Sending to EasyData API', [
                'payload' => $payload,
                'full_url' => $this->baseUrl . '/place-order',
                'base_url' => $this->baseUrl
            ]);

            try {
                if (empty($this->baseUrl)) {
                    Log::error('EasyData base URL is empty', [
                        'order_id' => $order->id,
                        'config_value' => config('services.easydata.base_url')
                    ]);
                    $order->update(['order_pusher_status' => 'failed']);
                    continue;
                }
                
                $credentials = base64_encode($this->username . ':' . $this->password);
                
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . $credentials,
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($this->baseUrl . '/place-order', $payload);

                Log::info('EasyData API Response', [
                    'status_code' => $response->status(),
                    'body' => $response->body()
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    Log::info('Detailed EasyData API response data', [
                        'order_id' => $order->id,
                        'raw_response' => $response->body(),
                        'parsed_response' => $responseData
                    ]);
                    
                    if ((isset($responseData['status']) && $responseData['status'] === true) || 
                        (isset($responseData['status']) && $responseData['status'] === 'success')) {
                        $easydataOrderId = $responseData['order_id'] ?? $responseData['order_reference'] ?? null;
                        
                        $updateData = ['order_pusher_status' => 'success'];
                        if ($easydataOrderId) {
                            $updateData['reference_id'] = $easydataOrderId;
                        }
                        
                        $order->update($updateData);
                        
                        Log::info('Order updated successfully', [
                            'order_id' => $order->id,
                            'easydata_order_id' => $easydataOrderId,
                            'reference_stored' => !empty($easydataOrderId)
                        ]);
                    } else {
                        $order->update(['order_pusher_status' => 'failed']);
                        Log::warning('EasyData API response indicates failure', [
                            'order_id' => $order->id,
                            'response' => $responseData
                        ]);
                    }
                } else {
                    $order->update(['order_pusher_status' => 'failed']);
                    
                    $responseBody = $response->body();
                    $statusCode = $response->status();
                    
                    if ($statusCode === 401) {
                        Log::error('EasyData API authentication failed - check credentials', [
                            'order_id' => $order->id,
                            'status_code' => $statusCode,
                            'username' => $this->username,
                            'response' => $responseBody
                        ]);
                    } else {
                        Log::error('EasyData API call failed', [
                            'order_id' => $order->id,
                            'status_code' => $statusCode,
                            'response' => $responseBody
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $order->update(['order_pusher_status' => 'failed']);
                Log::error('EasyData API Error', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        if ($processedItems === 0) {
            Log::info('No items were processed for EasyData order, keeping status as disabled', ['order_id' => $order->id]);
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

    private function getPackageSize($variant)
    {
        if (!isset($variant->variant_attributes['size'])) {
            Log::warning('Variant has no size attribute', ['variant_id' => $variant->id]);
            return null;
        }

        $size = strtolower(trim($variant->variant_attributes['size']));
        Log::info('Looking up EasyData package size', ['size' => $size]);
        
        // Extract numeric value from size (e.g., "5gb" -> 5)
        $numericSize = (int) filter_var($size, FILTER_SANITIZE_NUMBER_INT);
        
        if ($numericSize > 0) {
            Log::info('Successfully mapped to EasyData package size', [
                'original_size' => $size,
                'package_size' => $numericSize
            ]);
            return $numericSize;
        }
        
        Log::warning('Could not extract numeric size from variant', [
            'size' => $size,
            'numeric_size' => $numericSize
        ]);
        
        return null;
    }
}