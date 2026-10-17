<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class TskconnectOrderStatusSync
{
    public function syncSingleOrderStatus(Order $order, string $network): void
    {
        if (strtolower((string) $order->network) !== strtolower($network)) {
            return;
        }
        $this->syncOrder($order);
    }

    public function syncOrderStatuses(string $network): void
    {
        Order::with(['user', 'products' => function ($query) {
            $query->withPivot('product_variant_id');
        }])
            ->whereIn('status', ['pending', 'processing'])
            ->whereRaw('LOWER(network) = ?', [strtolower($network)])
            ->whereNotNull('reference_id')
            ->get()
            ->each(fn (Order $order) => $this->syncOrder($order));
    }

    private function syncOrder(Order $order): void
    {
        if (!str_starts_with((string) $order->reference_id, 'DWP-ORDER-')) {
            return;
        }

        $responseData = (new TskconnectOrderService())->fetchOrderStatus($order->reference_id);
        if (!$responseData) {
            return;
        }

        $data = $responseData['data'] ?? $responseData;
        $status = $data['status'] ?? $data['order']['status'] ?? null;
        if (!$status) {
            Log::warning('Tskconnect response did not include an order status', [
                'order_id' => $order->id,
                'response' => $responseData,
            ]);
            return;
        }

        $newStatus = $this->mapStatus((string) $status);
        if (!$newStatus || $newStatus === $order->status) {
            return;
        }

        $oldStatus = $order->status;
        $order->update(['status' => $newStatus]);

        if ($newStatus === 'completed' && $oldStatus !== 'completed' && $order->user?->phone) {
            $this->sendCompletionSms($order);
        }
    }

    private function mapStatus(string $status): ?string
    {
        return match (strtolower($status)) {
            'processed', 'completed', 'successful', 'delivered' => 'completed',
            'processing', 'pending' => 'processing',
            'failed', 'cancelled', 'rejected', 'refunded' => 'cancelled',
            default => null,
        };
    }

    private function sendCompletionSms(Order $order): void
    {
        try {
            $firstProduct = $order->products->first();
            $dataSize = '';
            if ($firstProduct && $firstProduct->pivot->product_variant_id) {
                $variant = \App\Models\ProductVariant::find($firstProduct->pivot->product_variant_id);
                if ($variant && isset($variant->variant_attributes['size'])) {
                    $dataSize = strtoupper($variant->variant_attributes['size']) . ' ';
                }
            }
            $productName = $firstProduct ? $firstProduct->name : 'Data/Airtime';
            $message = "Your order #{$order->id} for {$dataSize}{$productName} to {$order->beneficiary_number} ({$order->network}) has been completed. Total: GHS " . number_format($order->total, 2);
            (new MoolreSmsService())->sendSms($order->user->phone, $message);
        } catch (\Throwable $exception) {
            Log::warning('Tskconnect completion SMS failed', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
