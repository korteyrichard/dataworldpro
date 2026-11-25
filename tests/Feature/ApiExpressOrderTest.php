<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BundlePortalMtnExpressPusherDispatcher;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

test('API users can select a regular MTN product by network name', function () {
    Http::fake();

    $user = User::factory()->create([
        'role' => 'customer',
        'wallet_balance' => 50,
    ]);
    Sanctum::actingAs($user);

    $regularMtnProduct = Product::create([
        'name' => 'MTN',
        'network' => 'MTN',
        'product_type' => 'customer_product',
        'expiry' => 'non expiry',
    ]);
    ProductVariant::create([
        'product_id' => $regularMtnProduct->id,
        'price' => 8,
        'quantity' => '2GB',
        'status' => 'IN STOCK',
        'variant_attributes' => ['size' => '2GB'],
    ]);

    $response = $this->postJson('/api/v1/normal-orders', [
        'beneficiary_number' => '0241234567',
        'network' => 'MTN',
        'size' => '2GB',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('order.network', 'MTN')
        ->assertJsonPath('order.products.0.name', 'MTN');

    $order = Order::with('products')->findOrFail($response->json('order.reference_id'));
    expect($order->products->first()->id)->toBe($regularMtnProduct->id);
    expect(BundlePortalMtnExpressPusherDispatcher::isExpressOrder($order))->toBeFalse();
});

test('API users can select an existing MTN Express product by network name', function () {
    $user = User::factory()->create([
        'role' => 'customer',
        'wallet_balance' => 50,
    ]);
    Sanctum::actingAs($user);

    $regularMtnProduct = Product::create([
        'name' => 'MTN 1GB',
        'network' => 'MTN',
        'product_type' => 'customer_product',
        'expiry' => 'non expiry',
    ]);
    ProductVariant::create([
        'product_id' => $regularMtnProduct->id,
        'price' => 5,
        'quantity' => '1GB',
        'status' => 'IN STOCK',
        'variant_attributes' => ['size' => '1GB'],
    ]);

    $expressProduct = Product::create([
        'name' => 'MTN Express',
        'network' => 'MTN',
        'product_type' => 'customer_product',
        'expiry' => 'non expiry',
    ]);
    ProductVariant::create([
        'product_id' => $expressProduct->id,
        'price' => 10,
        'quantity' => '1GB',
        'status' => 'IN STOCK',
        'variant_attributes' => ['size' => '1GB'],
    ]);

    $response = $this->postJson('/api/v1/normal-orders', [
        'beneficiary_number' => '0241234567',
        'network' => 'MTN Express',
        'size' => '1GB',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('order.network', 'MTN')
        ->assertJsonPath('order.products.0.name', 'MTN Express');

    $order = Order::with('products')->findOrFail($response->json('order.reference_id'));
    expect($order->products->first()->id)->toBe($expressProduct->id);
    expect(BundlePortalMtnExpressPusherDispatcher::isExpressOrder($order))->toBeTrue();
});

test('API order selectors cannot be supplied together', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/normal-orders', [
        'beneficiary_number' => '0241234567',
        'network_id' => 5,
        'network' => 'MTN Express',
        'size' => '1GB',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['network_id', 'network']);
});
