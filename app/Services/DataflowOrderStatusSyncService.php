<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataflowOrderStatusSyncService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.dataflow.base_url', 'https://dataflowghana.com/api/v1');
        $this->apiKey = config('services.dataflow.api_key', '');
    }

    public function syncSingleOrderStatus(Order $order)
    {
        try {
            if (strtolower($order->network) === 'mtn') {
                $this->syncDataflowOrderStatus($order);
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync single Dataflow order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    public function syncOrderStatuses()
    {
        $processingOrders = Order::with(['user', 'products' => function($query) {
            $query->withPivot('product_variant_id');
        }])
        ->whereIn('status', ['pending', 'processing'])
        ->where('network', 'MTN')
        ->whereNotNull('reference_id')
        ->get();

        foreach ($processingOrders as $order) {
            try {
                $this->syncDataflowOrderStatus($order);
            } catch (\Exception $e) {
                Log::error('Failed to sync Dataflow order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function syncDataflowOrderStatus($order)
    {
        $referenceId = $order->reference_id;

        if (!$referenceId) {
            Log::warning('No reference ID found for Dataflow order', ['orderId' => $order->id]);
            return;
        }

        try {
            $endpoint = $this->baseUrl . '/transactions/' . $referenceId;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(20)->get($endpoint);

            Log::info('Dataflow status API response', [
                'order_id' => $order->id,
                'status_code' => $response->status(),
                'response_body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $externalStatus = $data['data']['status'] ?? ($data['status'] ?? null);
                if (!$externalStatus) {
                    Log::warning('Dataflow API response missing status', [
                        'order_id' => $order->id,
                        'response_data' => $data
                    ]);
                    return;
                }

                $newStatus = $this->mapDataflowStatus($externalStatus);

                if ($newStatus && $newStatus !== $order->status) {
                    $oldStatus = $order->status;
                    $order->update(['status' => $newStatus]);

                    Log::info('Dataflow order status updated', [
                        'orderId' => $order->id,
                        'oldStatus' => $oldStatus,
                        'newStatus' => $newStatus
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
                Log::warning('Dataflow API call unsuccessful', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Dataflow status check failed', [
                'orderId' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function mapDataflowStatus($externalStatus)
    {
        $statusMap = [
            'completed' => 'completed',
            'successful' => 'completed',
            'delivered' => 'completed',
            'processing' => 'processing',
            'pending' => 'processing',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled',
        ];

        return $statusMap[strtolower($externalStatus)] ?? null;
    }
}
