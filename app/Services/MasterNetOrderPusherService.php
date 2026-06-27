<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MasterNetOrderPusherService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.masternet.base_url');
        $this->apiKey = config('services.masternet.api_key');
    }

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for MasterNet DataHub API push', ['order_id' => $order->id]);

        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        $processedItems = 0;

        foreach ($items as $item) {
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);

            $sizeInGB = $variant && isset($variant->variant_attributes['size']) ? (int)filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $volume = $sizeInGB;
            $network = $this->getNetworkFromProduct($item->name);

            if (empty($beneficiaryPhone) || !$network || !$volume) {
                Log::warning('Skipping item for MasterNet DataHub - missing data', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'product' => $item->name
                ]);
                continue;
            }

            $processedItems++;

            $endpoint = $this->baseUrl . '/order/' . $network;
            $payload = [
                'type' => 'single',
                'volume' => (string) $volume,
                'phone' => $this->formatPhone($beneficiaryPhone),
                'offerSlug' => 'mtn',
                'metadata' => [
                    'idempotencyKey' => 'order_' . $order->id . '_' . $item->id . '_' . time()
                ],
            ];

            Log::info('Sending to MasterNet DataHub API', ['endpoint' => $endpoint, 'payload' => $payload]);

            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($endpoint, $payload);

                Log::info('MasterNet DataHub API Response', [
                    'status_code' => $response->status(),
                    'body' => $response->body()
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();

                    if (isset($responseData['reference'])) {
                        $referenceId = $responseData['reference'];

                        $order->update([
                            'reference_id' => $referenceId,
                            'order_pusher_status' => 'success'
                        ]);

                        $order->refresh();

                        Log::info('MasterNet DataHub order pushed successfully', [
                            'order_id' => $order->id,
                            'reference_id' => $referenceId
                        ]);
                    } else {
                        $order->update(['order_pusher_status' => 'failed']);
                        Log::warning('MasterNet DataHub API response missing reference', [
                            'order_id' => $order->id,
                            'response' => $responseData
                        ]);
                    }
                } else {
                    $order->update(['order_pusher_status' => 'failed']);
                    Log::error('MasterNet DataHub API call failed', [
                        'order_id' => $order->id,
                        'status_code' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            } catch (\Exception $e) {
                $order->update(['order_pusher_status' => 'failed']);
                Log::error('MasterNet DataHub API Error', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage()
                ]);
            }
        }

        if ($processedItems === 0) {
            Log::info('No items were processed for MasterNet DataHub order', ['order_id' => $order->id]);
        }
    }

    public function checkOrderStatus($identifier)
    {
        $endpoint = $this->baseUrl . '/order/status/' . $identifier;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(30)->get($endpoint);

            $responseData = $response->json();

            Log::info('MasterNet DataHub order status check response', [
                'identifier' => $identifier,
                'response' => $responseData
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('MasterNet DataHub order status check failed', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
            return '233' . substr($phone, 1);
        }
        return $phone;
    }

    private function getNetworkFromProduct($productName)
    {
        if (stripos($productName, 'mtn') !== false) {
            return 'mtn';
        }
        if (stripos($productName, 'telecel') !== false || stripos($productName, 'vodafone') !== false) {
            return 'telecel';
        }
        return null;
    }
}
