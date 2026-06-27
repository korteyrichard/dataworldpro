<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataflowOrderPusherService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.dataflow.base_url', 'https://dataflowghana.com/api/v1');
        $this->apiKey = config('services.dataflow.api_key', '');
    }

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for Dataflow API push', ['order_id' => $order->id]);

        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        $processedItems = 0;

        foreach ($items as $item) {
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);

            $size = $variant && isset($variant->variant_attributes['size']) ? $variant->variant_attributes['size'] : null;
            $networkId = $this->getNetworkIdFromProduct($item->name);

            if (empty($beneficiaryPhone) || !$networkId || !$size) {
                Log::warning('Skipping item for Dataflow - missing data', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'product' => $item->name
                ]);
                continue;
            }

            $processedItems++;

            $endpoint = $this->baseUrl . '/normal-orders';
            $payload = [
                'beneficiary_number' => $this->formatPhone($beneficiaryPhone),
                'network_id' => $networkId,
                'size' => strtoupper($size),
            ];

            Log::info('Sending to Dataflow API', ['endpoint' => $endpoint, 'payload' => $payload]);

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($endpoint, $payload);

                Log::info('Dataflow API Response', [
                    'status_code' => $response->status(),
                    'body' => $response->body()
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();

                    if (isset($responseData['order']['reference_id'])) {
                        $referenceId = $responseData['order']['reference_id'];

                        $order->update([
                            'reference_id' => $referenceId,
                            'order_pusher_status' => 'success'
                        ]);

                        $order->refresh();

                        Log::info('Dataflow order pushed successfully', [
                            'order_id' => $order->id,
                            'reference_id' => $referenceId
                        ]);
                    } else {
                        $order->update(['order_pusher_status' => 'failed']);
                        Log::warning('Dataflow API response missing reference_id', [
                            'order_id' => $order->id,
                            'response' => $responseData
                        ]);
                    }
                } else {
                    $order->update(['order_pusher_status' => 'failed']);
                    Log::error('Dataflow API call failed', [
                        'order_id' => $order->id,
                        'status_code' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            } catch (\Exception $e) {
                $order->update(['order_pusher_status' => 'failed']);
                Log::error('Dataflow API Error', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage()
                ]);
            }
        }

        if ($processedItems === 0) {
            Log::info('No items were processed for Dataflow order', ['order_id' => $order->id]);
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
            return 9;
        }
        return null;
    }
}
