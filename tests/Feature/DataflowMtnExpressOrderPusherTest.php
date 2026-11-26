<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\DataflowMtnExpressOrderPusherService;
use App\Services\MtnExpressPusherDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('Dataflow MTN Express pusher sends MTN Express orders through the express dispatcher', function () {
    $user = User::factory()->create();
    $product = Product::create([
        'name' => 'MTN Express',
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

    Setting::set('dataflow_mtn_express_order_pusher_enabled', '1');
    Http::fake(['*' => Http::response(['order' => ['reference_id' => 'dataflow-express-123']], 200)]);

    expect((new MtnExpressPusherDispatcher)->push($order))->toBeTrue();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/express-orders')
        && $request['network'] === 'MTN Express'
        && $request['size'] === '1GB');
    expect($order->fresh()->reference_id)->toBe('dataflow-express-123');
    expect($order->fresh()->order_pusher_status)->toBe('success');
});

test('Dataflow MTN Express pusher skips regular MTN products', function () {
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

    Http::fake();

    expect((new DataflowMtnExpressOrderPusherService)->pushOrderToApi($order))->toBeFalse();
    Http::assertNothingSent();
});

test('admin can toggle the Dataflow MTN Express pusher independently', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.dataflow-mtn-express-order-pusher.toggle'), ['enabled' => true])
        ->assertRedirect();

    expect(Setting::get('dataflow_mtn_express_order_pusher_enabled'))->toBe('1');
});
