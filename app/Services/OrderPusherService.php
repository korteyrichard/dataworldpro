<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderPusherService
{
    private $baseUrl = 'YOUR BASE URL';
    private $apiKey = 'YOUR API KEY';

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for API push', ['order_id' => $order->id]);
        
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number')->get();
        Log::info('Order has items', ['count' => $items->count()]);

        foreach ($items as $item) {
            Log::info('Processing item', ['name' => $item->name]);
            
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $sharedBundle = $item->pivot->quantity * 1000;
            $networkId = $this->getNetworkIdFromProduct($item->name);
            
            Log::info('Item details', [
                'product' => $item->name,
                'beneficiary' => $beneficiaryPhone,
                'bundle' => $sharedBundle,
                'network_id' => $networkId
            ]);

            if (empty($beneficiaryPhone)) {
                Log::warning('No beneficiary phone found for item, skipping');
                continue;
            }

            if (!$networkId || !$sharedBundle) {
                Log::warning('Missing required order data', [
                    'order_id' => $order->id,
                    'item_id' => $item->id
                ]);
                continue;
            }

            $endpoint = $this->baseUrl . '/buy-other-package';
            $payload = [
                'recipient_msisdn' => $this->formatPhone($beneficiaryPhone),
                'network_id' => $networkId,
                'shared_bundle' => $sharedBundle
            ];
            
            Log::info('Sending to API', ['endpoint' => $endpoint, 'payload' => $payload]);

            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($endpoint, $payload);

                Log::info('API Response', [
                    'status_code' => $response->status(),
                    'body' => $response->body()
                ]);

            } catch (\Exception $e) {
                Log::error('API Error', ['message' => $e->getMessage()]);
            }
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
            return 3;
        } elseif (stripos($productName, 'telecel') !== false) {
            return 2;
        } elseif (stripos($productName, 'AT Data (Instant)') !== false || stripos($productName, 'airtel') !== false || stripos($productName, 'tigo') !== false) {
            return 1;
        } elseif (stripos($productName, 'AT (Big Packages)') !== false) {
            return 4;
        }
        
        return 3;
    }
}