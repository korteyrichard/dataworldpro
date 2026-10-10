<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class MasterNetGhOrderPusherService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.masternetgh.base_url');
        $this->apiKey  = config('services.masternetgh.api_key');
    }

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for MasterNetGh API push', ['order_id' => $order->id]);

        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        $processedItems = 0;

        foreach ($items as $item) {
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);

            $sizeGb = $this->extractSizeGb($variant);
            $network = $this->getNetwork($item->name);

            if (empty($beneficiaryPhone) || !$sizeGb || !$network) {
                Log::warning('Skipping item for MasterNetGh - missing phone, size or network', [
                    'order_id' => $order->id,
                    'item_id'  => $item->id,
                    'product'  => $item->name,
                    'size_gb'  => $sizeGb,
                    'network'  => $network,
                ]);
                continue;
            }

            $bundleId = $this->resolveBundleId($network, $sizeGb);

            if (!$bundleId) {
                Log::warning('MasterNetGh bundle not found for size/network', [
                    'order_id' => $order->id,
                    'network'  => $network,
                    'size_gb'  => $sizeGb,
                ]);
                continue;
            }

            $processedItems++;

            $payload = [
                'bundleId'       => $bundleId,
                'phoneNumber'    => $this->formatPhone($beneficiaryPhone),
                'idempotencyKey' => Uuid::uuid4()->toString(),
            ];

            Log::info('Sending to MasterNetGh API', [
                'endpoint' => $this->baseUrl . '/agent/orders',
                'payload'  => $payload,
            ]);

            try {
                $response = Http::withHeaders([
                    'x-api-key'    => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])->timeout(30)->post($this->baseUrl . '/agent/orders', $payload);

                Log::info('MasterNetGh API Response', [
                    'status_code' => $response->status(),
                    'body'        => $response->body(),
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $publicId = $responseData['publicId'] ?? null;
                    $apiStatus = $responseData['status'] ?? null;

                    $failedStatuses = ['awaiting_mtn_approval', 'failed', 'cancelled', 'rejected'];

                    if ($publicId && !in_array($apiStatus, $failedStatuses)) {
                        $order->update([
                            'reference_id'        => $publicId,
                            'order_pusher_status' => 'success',
                        ]);
                        $order->refresh();

                        Log::info('MasterNetGh order pushed successfully', [
                            'order_id'  => $order->id,
                            'public_id' => $publicId,
                        ]);
                    } else {
                        $order->update(['order_pusher_status' => 'failed']);
                        Log::warning('MasterNetGh order push failed due to API status', [
                            'order_id'      => $order->id,
                            'api_status'    => $apiStatus,
                            'failure_reason' => $responseData['failureReason'] ?? null,
                            'public_id'     => $publicId,
                        ]);
                    }
                } else {
                    $order->update(['order_pusher_status' => 'failed']);
                    Log::error('MasterNetGh API call failed', [
                        'order_id'    => $order->id,
                        'status_code' => $response->status(),
                        'response'    => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                $order->update(['order_pusher_status' => 'failed']);
                Log::error('MasterNetGh API Error', [
                    'order_id' => $order->id,
                    'message'  => $e->getMessage(),
                ]);
            }
        }

        if ($processedItems === 0) {
            Log::info('No items processed for MasterNetGh order', ['order_id' => $order->id]);
        }
    }

    /**
     * Fetch bundles from the API (cached for 6 hours) and find the matching bundleId.
     */
    private function resolveBundleId(string $network, int $sizeGb): ?string
    {
        $bundles = Cache::remember('masternetgh_bundles', 21600, function () {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept'    => 'application/json',
                ])->timeout(15)->get($this->baseUrl . '/agent/bundles');

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('MasterNetGh failed to fetch bundles', [
                    'status_code' => $response->status(),
                    'body'        => $response->body(),
                ]);
            } catch (\Exception $e) {
                Log::error('MasterNetGh bundles fetch exception', ['error' => $e->getMessage()]);
            }

            return [];
        });

        foreach ($bundles as $bundle) {
            if (
                strtoupper($bundle['network']) === strtoupper($network) &&
                (int) $bundle['sizeGb'] === $sizeGb
            ) {
                return $bundle['id'];
            }
        }

        return null;
    }

    /**
     * Extract size in GB (integer) from a product variant.
     */
    private function extractSizeGb($variant): ?int
    {
        if (!$variant || !isset($variant->variant_attributes['size'])) {
            return null;
        }

        $size = $variant->variant_attributes['size'];
        // Handles "5GB", "5gb", "5 GB", or plain "5"
        $numeric = (int) filter_var($size, FILTER_SANITIZE_NUMBER_INT);

        return $numeric > 0 ? $numeric : null;
    }

    private function getNetwork(string $productName): ?string
    {
        if (stripos($productName, 'mtn') !== false) {
            return 'MTN';
        }
        if (stripos($productName, 'telecel') !== false || stripos($productName, 'vodafone') !== false) {
            return 'TELECEL';
        }
        return null;
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone) === 12 && str_starts_with($phone, '233')) {
            return '0' . substr($phone, 3);
        }
        if (str_starts_with($phone, '+233')) {
            return '0' . substr($phone, 4);
        }
        return $phone;
    }
}
