<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Webhook;
use App\Jobs\SendOrderWebhook;
use App\Services\CommissionService;

class OrderObserver
{
    public function __construct(private CommissionService $commissionService) {}

    public function created(Order $order): void
    {
        $this->commissionService->calculateAndCreateCommissions($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            try {
                if ($order->status === 'completed') {
                    $this->commissionService->makeCommissionsAvailable($order);
                } elseif (in_array($order->status, ['cancelled', 'refunded'])) {
                    $this->commissionService->reverseCommissions($order);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Commission processing failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->dispatchWebhooks($order);
        }
    }

    private function dispatchWebhooks(Order $order): void
    {
        $userId = $order->agent_id ?? $order->user_id;

        \Illuminate\Support\Facades\Log::info('Webhook dispatch triggered', [
            'order_id' => $order->id,
            'new_status' => $order->status,
            'user_id' => $userId,
        ]);

        $webhooks = Webhook::where('user_id', $userId)->where('is_active', true)->get();

        \Illuminate\Support\Facades\Log::info('Webhooks found', ['count' => $webhooks->count()]);

        $payload = [
            'event' => 'order.status_changed',
            'order_id' => $order->id,
            'reference_id' => $order->reference_id,
            'payment_reference' => $order->payment_reference,
            'status' => $order->status,
            'beneficiary_number' => $order->beneficiary_number,
            'network' => $order->network,
            'total' => $order->total,
            'timestamp' => now()->toIso8601String(),
        ];

        foreach ($webhooks as $webhook) {
            (new SendOrderWebhook($payload, $webhook->id))->handle();
        }
    }
}