<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TskconnectOrderService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.skanka5.base_url', 'https://tsk05.net/v1'), '/');
        $this->apiKey = (string) config('services.skanka5.api_key', '');
    }

    public function submitOrder(Order $order, string $network): bool
    {
        if ($this->apiKey === '') {
            Log::error('Tskconnect order submission skipped: API key is missing', ['order_id' => $order->id]);
            $order->update(['order_pusher_status' => 'failed']);
            return false;
        }

        $entries = [];
        $items = $order->products()
            ->withPivot('quantity', 'beneficiary_number', 'product_variant_id')
            ->get();

        foreach ($items as $item) {
            if (strtoupper((string) $item->network) !== strtoupper($network)) {
                continue;
            }

            $phone = $item->pivot->beneficiary_number ?: $order->beneficiary_number;
            $allocationGB = $this->allocationGB($item->pivot->product_variant_id);

            if (!$phone || !$allocationGB) {
                Log::warning('Tskconnect order item skipped due to missing recipient or bundle size', [
                    'order_id' => $order->id,
                    'product_id' => $item->id,
                ]);
                continue;
            }

            $entries[] = [
                'number' => preg_replace('/\D+/', '', $phone),
                'allocationGB' => $allocationGB,
            ];
        }

        if ($entries === []) {
            Log::warning('No valid order entries for Tskconnect', [
                'order_id' => $order->id,
                'network' => $network,
            ]);
            $order->update(['order_pusher_status' => 'failed']);
            return false;
        }

        $externalReference = 'DWP-ORDER-' . $order->id;
        $payload = [
            'externalReference' => $externalReference,
            'network' => strtoupper($network),
            'entries' => $entries,
        ];

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->withHeaders(['Idempotency-Key' => $externalReference])
                ->timeout(30)
                ->post($this->baseUrl . '/orders', $payload);

            $responseData = $response->json() ?? [];
            $data = $responseData['data'] ?? [];
            $acceptedEntries = $data['entries'] ?? $data['order']['entries'] ?? null;
            $hasAcceptedEntry = !is_array($acceptedEntries) || $acceptedEntries !== [];
            $success = $response->successful()
                && ($responseData['success'] ?? true) !== false
                && $hasAcceptedEntry;

            Log::log($success ? 'info' : 'error', 'Tskconnect order submission response', [
                'order_id' => $order->id,
                'network' => $network,
                'http_status' => $response->status(),
                'response' => $responseData,
            ]);

            if (!$success) {
                $order->update(['order_pusher_status' => 'failed']);
                return false;
            }

            $order->update([
                'reference_id' => $externalReference,
                'order_pusher_status' => 'success',
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Tskconnect order submission failed', [
                'order_id' => $order->id,
                'network' => $network,
                'error' => $exception->getMessage(),
            ]);
            $order->update(['order_pusher_status' => 'failed']);
            return false;
        }
    }

    public function fetchOrderStatus(string $reference): ?array
    {
        if ($this->apiKey === '') {
            Log::warning('Tskconnect status lookup skipped: API key is missing');
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(20)
                ->get($this->baseUrl . '/orders/reference/' . rawurlencode($reference));

            if (!$response->successful()) {
                Log::warning('Tskconnect status lookup failed', [
                    'reference' => $reference,
                    'http_status' => $response->status(),
                    'response' => $response->json(),
                ]);
                return null;
            }

            return $response->json() ?? [];
        } catch (\Throwable $exception) {
            Log::error('Tskconnect status lookup exception', [
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    private function allocationGB(?int $variantId): ?float
    {
        $variant = $variantId ? \App\Models\ProductVariant::find($variantId) : null;
        $size = $variant?->variant_attributes['size'] ?? null;

        if (is_numeric($size)) {
            return (float) $size > 0 ? (float) $size : null;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB)?/i', (string) $size, $matches)) {
            $amount = (float) $matches[1];
            if (strtoupper($matches[2] ?? '') === 'MB') {
                $amount /= 1000;
            }
            return $amount > 0 ? $amount : null;
        }

        return null;
    }
}
