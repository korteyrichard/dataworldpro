<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BundlePortalMtnOrderPusherService
{
    protected string $network = 'mtn';
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.bundleportal.base_url');
        $this->apiKey  = config('services.bundleportal.api_key');
    }

    public function pushOrderToApi(Order $order): bool
    {
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        $processed = 0;
        $successful = 0;

        foreach ($items as $lineNumber => $item) {
            if (stripos($item->name, 'mtn') === false) {
                continue;
            }

            $phone   = $this->formatPhone($item->pivot->beneficiary_number ?? '');
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            $gb      = $variant && isset($variant->variant_attributes['size'])
                ? (int) filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT)
                : 0;

            if (!$phone || !$gb) {
                Log::warning('BundlePortal MTN: missing data', ['order_id' => $order->id]);
                continue;
            }

            $processed++;
            $externalOrderId = 'SD-' . $order->id . '-' . strtoupper(str_replace('_', '', $this->network)) . '-' . ($lineNumber + 1);

            $payload = [
                'action'       => 'place_order',
                'network'      => $this->network,
                'recipient'    => $phone,
                'package_size' => $gb,
                'order_id'     => $externalOrderId,
            ];

            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'x-api-key'    => $this->apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($this->baseUrl, $payload);

                $data = $response->json();

                Log::info('BundlePortal MTN response', [
                    'order_id'    => $order->id,
                    'status_code' => $response->status(),
                    'response'    => $data,
                ]);

                if (!empty($data['success']) && !empty($data['data']['reference'])) {
                    $update = [
                        'reference_id' => $data['data']['reference'],
                        'order_pusher_status' => 'success',
                    ];

                    // Keep a successful paid order successful if the production
                    // database has not yet received the Bundle Portal reference migration.
                    if (Schema::hasColumn('orders', 'bundleportal_reference_id')) {
                        $update['bundleportal_reference_id'] = $data['data']['reference'];
                    }

                    $order->update($update);
                    $successful++;
                } else {
                    $order->update(['order_pusher_status' => 'failed']);
                    Log::error('BundlePortal MTN API error', ['response' => $data]);
                }
            } catch (\Exception $e) {
                $order->update(['order_pusher_status' => 'failed']);
                Log::error('BundlePortal MTN exception', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        if ($processed === 0) {
            $order->update(['order_pusher_status' => 'failed']);
            Log::warning('BundlePortal MTN: no MTN items were submitted', ['order_id' => $order->id]);
        }

        return $successful > 0;
    }

    public function checkOrderStatus(string $reference): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'    => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl, [
                    'action'    => 'check_order',
                    'reference' => $reference,
                ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('BundlePortal MTN status check failed', ['reference' => $reference, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 9) {
            return '0' . $phone;
        }
        return $phone;
    }
}
