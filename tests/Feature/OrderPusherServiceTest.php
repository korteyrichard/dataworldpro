<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderPusherService;
use App\Services\OrderStatusSyncService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

test('Jaybart order pusher sends the documented single-purchase request with auth and a stable ref', function () {
    config([
        'app.order_pusher_base_url' => 'https://agent.jaybartservices.com/api/v1',
        'app.order_pusher_api_key' => 'test-api-key',
    ]);

    $user = User::factory()->create();
    $product = Product::create([
        'name' => 'MTN 1GB',
        'network' => 'MTN',
        'product_type' => 'customer_product',
        'expiry' => 'non expiry',
    ]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'price' => 10,
        'quantity' => '1GB',
        'status' => 'IN STOCK',
        'variant_attributes' => ['size' => '1GB'],
    ]);
    $order = Order::create([
        'user_id' => $user->id,
        'total' => 10,
        'network' => 'MTN',
        'beneficiary_number' => '0241234567',
    ]);
    $order->products()->attach($product->id, [
        'quantity' => 1,
        'price' => 10,
        'beneficiary_number' => '0241234567',
        'product_variant_id' => $variant->id,
    ]);
    $itemId = $order->products()->withPivot('id')->first()->pivot->id;

    Http::fake([
        '*' => Http::response([
            'success' => true,
            'message' => 'Purchase successful',
            'transaction_code' => 'jaybart-txn-123',
        ]),
    ]);

    (new OrderPusherService)->pushOrderToApi($order);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://agent.jaybartservices.com/api/v1/buy-other-package'
        && $request->hasHeader('x-api-key', 'test-api-key')
        && $request['recipient_msisdn'] === '0241234567'
        && $request['network_id'] === 3
        && $request['shared_bundle'] === 1000
        && $request['ref'] === "order-{$order->id}-item-{$itemId}");

    expect($order->fresh()->reference_id)->toBe('jaybart-txn-123')
        ->and($order->fresh()->order_pusher_status)->toBe('success');
});

test('manual Jaybart status sync uses the configured base URL and API key', function () {
    config([
        'app.order_pusher_base_url' => 'https://jaybart.test/api/v1',
        'app.order_pusher_api_key' => 'test-api-key',
    ]);
    Setting::set('skanka5_order_pusher_enabled', '0');

    $order = Order::create([
        'user_id' => User::factory()->create()->id,
        'reference_id' => 'jaybart-manual-123',
        'total' => 10,
        'network' => 'MTN',
        'beneficiary_number' => '0241234567',
    ]);

    Http::fake([
        '*' => Http::response(['order_items' => [['status' => 'delivered']]]),
    ]);

    (new OrderStatusSyncService)->syncSingleOrderStatus($order);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://jaybart.test/api/v1/fetch-other-network-transaction'
        && $request->hasHeader('x-api-key', 'test-api-key')
        && $request['transaction_id'] === 'jaybart-manual-123');
    expect($order->fresh()->status)->toBe('completed');
});

test('failed Jaybart pusher status sync uses the configured base URL and API key', function () {
    config([
        'app.order_pusher_base_url' => 'https://jaybart.test/api/v1',
        'app.order_pusher_api_key' => 'test-api-key',
    ]);

    $order = Order::create([
        'user_id' => User::factory()->create()->id,
        'reference_id' => 'jaybart-failed-123',
        'order_pusher_status' => 'failed',
        'total' => 10,
        'network' => 'mtn',
        'beneficiary_number' => '0241234567',
    ]);

    Http::fake([
        '*' => Http::response(['order_items' => [['status' => 'processing']]]),
    ]);

    Artisan::call('orders:sync-failed');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://jaybart.test/api/v1/fetch-other-network-transaction'
        && $request->hasHeader('x-api-key', 'test-api-key')
        && $request['transaction_id'] === 'jaybart-failed-123');
    expect($order->fresh()->order_pusher_status)->toBe('success');
});
