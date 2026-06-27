<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CodeCraftMtnOrderPusherService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.codecraft_mtn.base_url');
        $this->apiKey = config('services.codecraft_mtn.api_key');
    }

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for CodeCraft MTN API push', ['order_id' => $order->id]);

        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        $processedItems = 0;

        foreach ($items as $item) {
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            $gig = $variant && isset($variant->variant_attributes['size']) ? (int)filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT) : 0;

            if (stripos($item->name, 'mtn') === false) {
                Log::info('Non-MTN product, skipping CodeCraft MTN processing', ['product_name' => $item->name]);
                continue;
            }

            if (empty($beneficiaryPhone) || !$gig) {
                Log::warning('Missing required order data for CodeCraft MTN', [
                    'order_id' => $order->id,
                    'beneficiary' => $beneficiaryPhone,
                    'gig' => $gig
                ]);
                continue;
            }

            $processedItems++;

            $payload = [
                'recipient_number' => $this->formatPhone($beneficiaryPhone),
                'gig' => (string)$gig,
                'network' => 'MTN'
            ];

            Log::info('Sending to CodeCraft MTN API', ['payload' => $payload, 'api_key_present' => !empty($this->apiKey)]);

            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'x-api-key' => $this->apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])
                    ->post($this->baseUrl . '/initiate.php', $payload);

                $responseData = $response->json();

                Log::info('CodeCraft MTN API Response', [
                    'status_code' => $response->status(),
                    'response' => $responseData
                ]);

                if (isset($responseData['status']) && $responseData['status'] == 200 && isset($responseData['reference_id'])) {
                    $order->update([
                        'reference_id' => $responseData['reference_id'],
                        'order_pusher_status' => 'success'
                    ]);
                    Log::info('Order sent successfully to CodeCraft MTN', ['reference_id' => $responseData['reference_id']]);
                } else {
                    $order->update(['order_pusher_status' => 'failed']);
                    Log::error('CodeCraft MTN API Error', [
                        'status' => $responseData['status'] ?? 'unknown',
                        'message' => $responseData['message'] ?? 'Unknown error'
                    ]);
                }
            } catch (\Exception $e) {
                $order->update(['order_pusher_status' => 'failed']);
                Log::error('CodeCraft MTN API Exception', ['message' => $e->getMessage()]);
            }
        }

        if ($processedItems === 0) {
            Log::info('No MTN items processed for CodeCraft MTN', ['order_id' => $order->id]);
        }
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
            return $phone;
        }

        if (strlen($phone) == 9) {
            return '0' . $phone;
        }

        return $phone;
    }
}
