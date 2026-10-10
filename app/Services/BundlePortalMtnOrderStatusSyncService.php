<?php

namespace App\Services;

use App\Models\Order;
use App\Services\CommissionService;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Log;

class BundlePortalMtnOrderStatusSyncService
{
    private BundlePortalMtnOrderPusherService $pusher;

    public function __construct()
    {
        $this->pusher = new BundlePortalMtnOrderPusherService();
    }

    public function syncPendingOrders(): void
    {
        $orders = Order::whereIn('status', ['pending', 'processing'])
            ->where('order_pusher_status', 'success')
            ->whereNotNull('reference_id')
            ->where('network', 'like', '%mtn%')
            ->get();

        Log::info('BundlePortal MTN sync started', ['count' => $orders->count()]);

        foreach ($orders as $order) {
            $this->syncOrder($order);
        }
    }

    private function syncOrder(Order $order): void
    {
        $data = $this->pusher->checkOrderStatus($order->reference_id);

        if (!$data || empty($data['data']['status'])) {
            return;
        }

        $newStatus = $this->mapStatus($data['data']['status']);

        if (!$newStatus || $newStatus === $order->status) {
            return;
        }

        $order->update(['status' => $newStatus]);

        Log::info('BundlePortal MTN status updated', [
            'order_id'   => $order->id,
            'new_status' => $newStatus,
        ]);

        if ($newStatus === 'completed') {
            $this->sendCompletionSms($order);
            (new CommissionService())->makeCommissionsAvailable($order);
        } elseif ($newStatus === 'cancelled') {
            (new CommissionService())->reverseCommissions($order);
        }
    }

    private function mapStatus(string $status): ?string
    {
        return match (strtolower($status)) {
            'processing', 'pending', 'cached' => 'processing',
            'completed', 'delivered', 'successful' => 'completed',
            'failed', 'cancelled' => 'cancelled',
            default => null,
        };
    }

    private function sendCompletionSms(Order $order): void
    {
        try {
            if (!$order->user || !$order->user->phone) {
                return;
            }
            $firstProduct = $order->products->first();
            $dataSize = '';
            if ($firstProduct && $firstProduct->pivot->product_variant_id) {
                $variant = \App\Models\ProductVariant::find($firstProduct->pivot->product_variant_id);
                if ($variant && isset($variant->variant_attributes['size'])) {
                    $dataSize = strtoupper($variant->variant_attributes['size']) . ' ';
                }
            }
            $productName = $firstProduct ? $firstProduct->name : 'MTN Data';
            $message = "Your order #{$order->id} for {$dataSize}{$productName} to {$order->beneficiary_number} has been completed. Total: GHS " . number_format($order->total, 2);
            (new MoolreSmsService())->sendSms($order->user->phone, $message);
        } catch (\Exception $e) {
            Log::warning('BundlePortal MTN SMS failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
