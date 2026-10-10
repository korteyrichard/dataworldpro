<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MasterNetGhOrderStatusSyncService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.masternetgh.base_url');
        $this->apiKey = config('services.masternetgh.api_key');
    }

    public function syncSingleOrderStatus(Order $order)
    {
        try {
            $this->syncOrderStatus($order);
        } catch (\Exception $e) {
            Log::error('Failed to sync single MasterNetGh order status', [
                'orderId' => $order->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function syncOrderStatuses()
    {
        $orders = Order::with(['user', 'products' => function ($q) {
            $q->withPivot('product_variant_id');
        }])
            ->whereIn('status', ['pending', 'processing'])
            ->where('reference_id', 'LIKE', 'MNG-%')
            ->where('order_pusher_status', 'success')
            ->get();

        foreach ($orders as $order) {
            try {
                $this->syncOrderStatus($order);
            } catch (\Exception $e) {
                Log::error('Failed to sync MasterNetGh order status', [
                    'orderId' => $order->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    private function syncOrderStatus(Order $order)
    {
        $referenceId = $order->reference_id;

        if (!$referenceId) {
            Log::warning('No reference ID for MasterNetGh order', ['orderId' => $order->id]);
            return;
        }

        try {
            $endpoint = $this->baseUrl . '/agent/orders/' . $referenceId;

            Log::info('MasterNetGh status check', [
                'order_id'    => $order->id,
                'reference_id' => $referenceId,
                'endpoint'    => $endpoint,
            ]);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout(20)->get($endpoint);

            if ($response->successful()) {
                $data = $response->json();

                if (!isset($data['status'])) {
                    Log::warning('MasterNetGh API response missing status', [
                        'order_id' => $order->id,
                        'response' => $data,
                    ]);
                    return;
                }

                $newStatus = $this->mapStatus($data['status']);

                if ($newStatus && $newStatus !== $order->status) {
                    $oldStatus = $order->status;
                    $order->update(['status' => $newStatus]);

                    Log::info('MasterNetGh order status updated', [
                        'orderId'   => $order->id,
                        'oldStatus' => $oldStatus,
                        'newStatus' => $newStatus,
                    ]);

                    if ($newStatus === 'completed' && $oldStatus !== 'completed' && $order->user && $order->user->phone) {
                        $smsService = new MoolreSmsService();
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
                        $smsService->sendSms($order->user->phone, $message);
                    }
                }
            } else {
                Log::warning('MasterNetGh status API call unsuccessful', [
                    'order_id'    => $order->id,
                    'status_code' => $response->status(),
                    'response'    => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('MasterNetGh status check failed', [
                'orderId' => $order->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function mapStatus($externalStatus)
    {
        $map = [
            'delivered'              => 'completed',
            'completed'              => 'completed',
            'successful'             => 'completed',
            'processed'              => 'completed',
            'processing'             => 'processing',
            'pending'                => 'processing',
            'awaiting_mtn_approval'  => 'processing',
        ];

        return $map[strtolower($externalStatus)] ?? null;
    }
}
