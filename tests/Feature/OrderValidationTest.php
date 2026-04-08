<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class OrderValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $product;
    protected $variant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'wallet_balance' => 100.00,
            'role' => 'customer'
        ]);
        
        $this->product = Product::create([
            'name' => 'Test Data Bundle',
            'network' => 'mtn',
            'product_type' => 'customer_product',
            'status' => 'active'
        ]);
        
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'price' => 10.00,
            'quantity' => 5,
            'status' => 'active',
            'variant_attributes' => ['size' => '1GB']
        ]);
    }

    /** @test */
    public function it_validates_beneficiary_number_format()
    {
        Sanctum::actingAs($this->user);

        // Test invalid beneficiary number (less than 10 digits)
        $response = $this->postJson('/api/v1/normal-orders', [
            'beneficiary_number' => '123456789',
            'network_id' => $this->product->id,
            'size' => '1GB'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['beneficiary_number']);

        // Test invalid beneficiary number (more than 10 digits)
        $response = $this->postJson('/api/v1/normal-orders', [
            'beneficiary_number' => '12345678901',
            'network_id' => $this->product->id,
            'size' => '1GB'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['beneficiary_number']);

        // Test invalid beneficiary number (contains letters)
        $response = $this->postJson('/api/v1/normal-orders', [
            'beneficiary_number' => '123456789a',
            'network_id' => $this->product->id,
            'size' => '1GB'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['beneficiary_number']);
    }

    /** @test */
    public function it_prevents_orders_when_variant_is_out_of_stock()
    {
        Sanctum::actingAs($this->user);
        
        // Set variant quantity to 0
        $this->variant->update(['quantity' => 0]);

        $response = $this->postJson('/api/v1/normal-orders', [
            'beneficiary_number' => '1234567890',
            'network_id' => $this->product->id,
            'size' => '1GB'
        ]);

        $response->assertStatus(400)
                ->assertJson(['error' => 'Product variant is out of stock']);
    }

    /** @test */
    public function it_prevents_duplicate_orders_with_same_beneficiary_number()
    {
        Sanctum::actingAs($this->user);
        
        // Create an existing pending order
        Order::create([
            'user_id' => $this->user->id,
            'total' => 10.00,
            'beneficiary_number' => '1234567890',
            'network' => 'mtn',
            'status' => 'pending'
        ]);

        $response = $this->postJson('/api/v1/normal-orders', [
            'beneficiary_number' => '1234567890',
            'network_id' => $this->product->id,
            'size' => '1GB'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['beneficiary_number']);
    }

    /** @test */
    public function it_allows_orders_with_valid_data_and_decrements_stock()
    {
        Sanctum::actingAs($this->user);
        
        $initialQuantity = $this->variant->quantity;

        $response = $this->postJson('/api/v1/normal-orders', [
            'beneficiary_number' => '1234567890',
            'network_id' => $this->product->id,
            'size' => '1GB'
        ]);

        $response->assertStatus(201)
                ->assertJson(['message' => 'Order created successfully']);

        // Check that stock was decremented
        $this->variant->refresh();
        $this->assertEquals($initialQuantity - 1, $this->variant->quantity);
    }
}