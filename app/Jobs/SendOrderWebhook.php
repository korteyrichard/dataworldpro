<?php

namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOrderWebhook
{
    public function __construct(
        private array $payload,
        private int $webhookId
    ) {}

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (!$webhook || !$webhook->is_active) {
            return;
        }

        $signature = hash_hmac('sha256', json_encode($this->payload), $webhook->secret);

        try {
            Http::timeout(10)
                ->withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'Content-Type' => 'application/json',
                ])
                ->post($webhook->url, $this->payload);
        } catch (\Exception $e) {
            Log::error('Webhook delivery error', [
                'webhook_id' => $webhook->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
