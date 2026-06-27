<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MasterNetOrderStatusSyncService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.masternet.base_url');
        $this->apiKey = config('services.masternet.api_key');
    }

    public function syncSingleOrderStatus(Order $order)
    {
        try {
            $this->syncOrderStatus($order);
        } catch (\Exception $e) {
            Log::error('Failed to sync single MasterNet DataHub order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    public function syncOrderStatuses()
    {
        $processingOrders = Order::with(['user', 'products' => function($query) {
            $query->withPivot('product_variant_id');
        }])
        ->whereIn('status', ['pending', 'processing'])
        ->whereNotNull('reference_id')
        ->get();

        foreach ($processingOrders as $order) {
            try {
                $this->syncOrderStatus($order);
            } catch (\Exception $e) {
                Log::error('Failed to sync MasterNet DataHub order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function syncOrderStatus($order)
    {
        $referenceId = $order->reference_id;

        if (!$referenceId) {
            Log::warning('No reference ID found for MasterNet DataHub order', ['orderId' => $order->id]);
            return;
        }

        try {
            $endpoint = $this->baseUrl . '/order/status/' . $referenceId;

            Log::info('Making MasterNet DataHub status API call', [
                'order_id' => $order->id,
                'reference_id' => $referenceId,
                'api_endpoint' => $endpoint
            ]);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(20)->get($endpoint);

            if ($response->successful()) {
                $data = $response->json();

                if (!isset($data['order']['status'])) {
                    Log::warning('MasterNet DataHub API response missing status field', [
                        'order_id' => $order->id,
                        'response_data' => $data
                    ]);
                    return;
                }

                $externalStatus = $data['order']['status'];
                $newStatus = $this->mapStatus($externalStatus);

                if ($newStatus && $newStatus !== $order->status) {
                    $oldStatus = $order->status;
                    $order->update(['status' => $newStatus]);

                    Log::info('MasterNet DataHub order status updated', [
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
                Log::warning('MasterNet DataHub API call unsuccessful', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('MasterNet DataHub status check failed', [
                'orderId' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function mapStatus($externalStatus)
    {
        $statusMap = [
            'delivered' => 'completed',
            'completed' => 'completed',
            'successful' => 'completed',
            'processed' => 'completed',
            'processing' => 'processing',
            'pending' => 'processing',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled',
            'rejected' => 'cancelled',
        ];

        return $statusMap[strtolower($externalStatus)] ?? null;
    }
}
