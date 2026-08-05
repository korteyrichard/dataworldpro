<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestInboundWebhooks extends Command
{
    protected $signature = 'webhook:test-inbound
                            {--provider= : Specific provider to test (masternetdatahub, jessco, skanka5)}
                            {--reference= : Order reference_id to use in the test payload}
                            {--status=completed : Status to send in the payload}';

    protected $description = 'Test inbound webhooks by sending a simulated POST request to each endpoint';

    private array $providers = [
        'masternetdatahub' => '/api/webhook/masternetdatahub',
        'jessco'           => '/api/webhook/jessco',
        'skanka5'          => '/api/webhook/skanka5',
    ];

    public function handle(): int
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $provider = $this->option('provider');
        $referenceId = $this->option('reference') ?? 'TEST-REF-' . now()->timestamp;
        $status = $this->option('status');

        $targets = $provider
            ? [$provider => $this->providers[$provider] ?? null]
            : $this->providers;

        if ($provider && !isset($this->providers[$provider])) {
            $this->error("Unknown provider: {$provider}. Valid options: " . implode(', ', array_keys($this->providers)));
            return self::FAILURE;
        }

        $this->info("Testing inbound webhooks against: {$baseUrl}");
        $this->info("Reference ID: {$referenceId} | Status: {$status}");
        $this->newLine();

        $allPassed = true;

        foreach ($targets as $name => $path) {
            $url = $baseUrl . $path;
            $secret = config("services.webhooks.{$name}");

            $payload = [
                'reference_id' => $referenceId,
                'status'       => $status,
            ];

            $headers = ['Accept' => 'application/json'];

            if ($secret) {
                $headers['X-Webhook-Signature'] = $secret;
            }

            $this->line("→ <comment>{$name}</comment> — {$url}");

            try {
                $response = Http::withHeaders($headers)
                    ->timeout(10)
                    ->post($url, $payload);

                $code = $response->status();
                $body = $response->json() ?? $response->body();

                if ($response->successful()) {
                    $this->line("  <info>✔ {$code}</info> " . (is_array($body) ? json_encode($body) : $body));
                } else {
                    $this->line("  <error>✘ {$code}</error> " . (is_array($body) ? json_encode($body) : $body));
                    $allPassed = false;
                }
            } catch (\Exception $e) {
                $this->line("  <error>✘ Connection failed:</error> " . $e->getMessage());
                $allPassed = false;
            }

            $this->newLine();
        }

        return $allPassed ? self::SUCCESS : self::FAILURE;
    }
}
