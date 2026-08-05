<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeOrder(string $referenceId, string $status = 'pending'): Order
{
    return Order::create([
        'reference_id'       => $referenceId,
        'status'             => $status,
        'beneficiary_number' => '0241234567',
        'network'            => 'MTN',
        'total'              => 10.00,
        'payment_method'     => 'paystack',
    ]);
}

function jesscHeaders(string $secret, string $body): array
{
    return ['X-Webhook-Signature' => hash_hmac('sha256', $body, $secret)];
}

// ─── Jessco Webhook ──────────────────────────────────────────────────────────

describe('Jessco webhook (/api/webhook/jessco)', function () {

    it('hits the endpoint and returns 200 without a secret configured', function () {
        config(['services.webhooks.jessco' => null]);
        makeOrder('REF001');

        $response = $this->postJson('/api/webhook/jessco', [
            'reference_id' => 'REF001',
            'status'       => 'delivered',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Webhook processed']);
    });

    it('updates order status to completed on delivered', function () {
        config(['services.webhooks.jessco' => null]);
        makeOrder('REF002', 'pending');

        $this->postJson('/api/webhook/jessco', [
            'reference_id' => 'REF002',
            'status'       => 'delivered',
        ])->assertStatus(200);

        expect(Order::where('reference_id', 'REF002')->value('status'))->toBe('completed');
    });

    it('updates order status to cancelled on failed', function () {
        config(['services.webhooks.jessco' => null]);
        makeOrder('REF003', 'pending');

        $this->postJson('/api/webhook/jessco', [
            'reference_id' => 'REF003',
            'status'       => 'failed',
        ])->assertStatus(200);

        expect(Order::where('reference_id', 'REF003')->value('status'))->toBe('cancelled');
    });

    it('returns 422 when reference_id or status is missing', function () {
        config(['services.webhooks.jessco' => null]);

        $this->postJson('/api/webhook/jessco', ['status' => 'delivered'])
             ->assertStatus(422);

        $this->postJson('/api/webhook/jessco', ['reference_id' => 'REF999'])
             ->assertStatus(422);
    });

    it('returns 401 when secret is set but no signature header is sent', function () {
        config(['services.webhooks.jessco' => 'test-secret']);

        $this->postJson('/api/webhook/jessco', [
            'reference_id' => 'REF004',
            'status'       => 'delivered',
        ])->assertStatus(401);
    });

    it('accepts request when valid HMAC signature is provided', function () {
        $secret = 'test-secret';
        config(['services.webhooks.jessco' => $secret]);
        makeOrder('REF005', 'pending');

        $body    = json_encode(['reference_id' => 'REF005', 'status' => 'delivered']);
        $headers = jesscHeaders($secret, $body);

        $this->postJson('/api/webhook/jessco', json_decode($body, true), $headers)
             ->assertStatus(200);

        expect(Order::where('reference_id', 'REF005')->value('status'))->toBe('completed');
    });

    it('accepts purchase_id as fallback for reference_id', function () {
        config(['services.webhooks.jessco' => null]);
        makeOrder('REF006', 'pending');

        $this->postJson('/api/webhook/jessco', [
            'purchase_id' => 'REF006',
            'status'      => 'completed',
        ])->assertStatus(200);

        expect(Order::where('reference_id', 'REF006')->value('status'))->toBe('completed');
    });
});

// ─── Skanka5 Webhook ─────────────────────────────────────────────────────────

describe('Skanka5 webhook (/api/webhook/skanka5)', function () {

    it('hits the endpoint and returns 200 without a secret configured', function () {
        config(['services.webhooks.skanka5' => null]);
        makeOrder('SKA001');

        $response = $this->postJson('/api/webhook/skanka5', [
            'reference_id' => 'SKA001',
            'status'       => 'delivered',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Webhook processed']);
    });

    it('updates order status to completed on successful', function () {
        config(['services.webhooks.skanka5' => null]);
        makeOrder('SKA002', 'pending');

        $this->postJson('/api/webhook/skanka5', [
            'reference_id' => 'SKA002',
            'status'       => 'successful',
        ])->assertStatus(200);

        expect(Order::where('reference_id', 'SKA002')->value('status'))->toBe('completed');
    });

    it('updates order status to cancelled on rejected', function () {
        config(['services.webhooks.skanka5' => null]);
        makeOrder('SKA003', 'pending');

        $this->postJson('/api/webhook/skanka5', [
            'reference_id' => 'SKA003',
            'status'       => 'rejected',
        ])->assertStatus(200);

        expect(Order::where('reference_id', 'SKA003')->value('status'))->toBe('cancelled');
    });

    it('returns 422 when reference_id or status is missing', function () {
        config(['services.webhooks.skanka5' => null]);

        $this->postJson('/api/webhook/skanka5', ['status' => 'delivered'])
             ->assertStatus(422);

        $this->postJson('/api/webhook/skanka5', ['reference_id' => 'SKA999'])
             ->assertStatus(422);
    });

    it('returns 401 when secret is set but no signature header is sent', function () {
        config(['services.webhooks.skanka5' => 'test-secret']);

        $this->postJson('/api/webhook/skanka5', [
            'reference_id' => 'SKA004',
            'status'       => 'delivered',
        ])->assertStatus(401);
    });

    it('accepts request when valid HMAC signature is provided', function () {
        $secret = 'test-secret';
        config(['services.webhooks.skanka5' => $secret]);
        makeOrder('SKA005', 'pending');

        $body    = json_encode(['reference_id' => 'SKA005', 'status' => 'successful']);
        $headers = jesscHeaders($secret, $body);

        $this->postJson('/api/webhook/skanka5', json_decode($body, true), $headers)
             ->assertStatus(200);

        expect(Order::where('reference_id', 'SKA005')->value('status'))->toBe('completed');
    });

    it('accepts order_id as fallback for reference_id', function () {
        config(['services.webhooks.skanka5' => null]);
        makeOrder('SKA006', 'pending');

        $this->postJson('/api/webhook/skanka5', [
            'order_id' => 'SKA006',
            'status'   => 'completed',
        ])->assertStatus(200);

        expect(Order::where('reference_id', 'SKA006')->value('status'))->toBe('completed');
    });
});
