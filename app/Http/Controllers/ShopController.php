<?php

namespace App\Http\Controllers;

use App\Models\AgentShop;
use App\Models\Order;
use App\Models\Setting;
use App\Services\AgentService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ShopController extends Controller
{
    public function __construct(private AgentService $agentService) {}

    public function show(string $slug)
    {
        $shop = AgentShop::where('slug', $slug)
            ->where('is_active', true)
            ->with('agent')
            ->firstOrFail();

        $products = $this->agentService->getShopProducts($shop);

        return inertia('Shop/Show', [
            'shop' => $shop,
            'products' => $products,
            'agent' => [
                'name' => $shop->agent->name,
                'phone' => $shop->agent->phone
            ],
            'trackOrderVideoUrl' => Setting::get('track_order_video_url', '')
        ]);
    }

    public function addToCart(Request $request, string $slug)
    {
        $request->validate([
            'agent_product_id' => 'required|exists:agent_products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $shop = AgentShop::where('slug', $slug)->firstOrFail();
        
        // Store agent_id in session for checkout
        session(['agent_id' => $shop->user_id]);

        // Use existing cart logic but with agent pricing
        // This would integrate with your existing cart system
        
        return response()->json(['message' => 'Added to cart']);
    }

    public function orderSuccess(string $slug, Order $order)
    {
        $shop = AgentShop::where('slug', $slug)
            ->where('is_active', true)
            ->with('agent')
            ->firstOrFail();

        // Verify the order belongs to this shop
        if ($order->agent_id !== $shop->user_id) {
            abort(404, 'Order not found');
        }

        // Only show success page for completed orders
        if (!in_array($order->status, ['completed', 'pending'])) {
            abort(404, 'Order not found');
        }

        // Load order with products
        $order->load(['products']);

        return inertia('Shop/OrderSuccess', [
            'order' => [
                'id' => $order->id,
                'total' => $order->total,
                'total_amount' => $order->total_amount,
                'payment_reference' => $order->payment_reference,
                'buyer_email' => $order->buyer_email,
                'beneficiary_number' => $order->beneficiary_number,
                'network' => $order->network,
                'status' => $order->status,
                'created_at' => $order->created_at->toISOString(),
                'products' => $order->products->map(function($product) {
                    return [
                        'name' => $product->name,
                        'network' => $product->network,
                    ];
                })
            ],
            'agent' => [
                'name' => $shop->agent->name
            ],
            'shop' => [
                'name' => $shop->name,
                'slug' => $shop->slug,
                'primary_color' => $shop->primary_color,
                'background_color' => $shop->background_color
            ]
        ]);
    }

    private function verifyPaystackPayment($reference)
    {
        $secretKey = config('services.paystack.secret_key');
        
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
        ])->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful()) {
            return [
                'status' => true,
                'data' => $response->json()['data']
            ];
        }

        return ['status' => false];
    }

    public function createOrderFromPayment(Request $request, string $slug)
    {
        $request->validate([
            'payment_reference' => 'required|string',
            'agent_product_id' => 'required|exists:agent_products,id',
            'beneficiary_number' => 'required|string'
        ]);

        $shop = AgentShop::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Verify payment hasn't been used for an order already
        $existingOrder = Order::where('payment_reference', $request->payment_reference)->first();
        if ($existingOrder) {
            return back()->withErrors(['payment_reference' => 'This payment has already been used for an order.']);
        }

        // Verify the payment with Paystack
        $paymentVerification = $this->verifyPaystackPayment($request->payment_reference);
        
        if (!$paymentVerification['status'] || $paymentVerification['data']['status'] !== 'success') {
            return back()->withErrors(['payment_reference' => 'Payment verification failed.']);
        }

        $paymentData = $paymentVerification['data'];
        $agentProduct = \App\Models\AgentProduct::with(['product', 'productVariant', 'agentShop.agent'])
            ->find($request->agent_product_id);

        // Verify the product belongs to this shop
        if ($agentProduct->agentShop->slug !== $slug) {
            return back()->withErrors(['agent_product_id' => 'Invalid product selection.']);
        }

        // Verify the payment amount matches the product price
        $paymentAmount = $paymentData['amount'] / 100;
        if (abs(floatval($agentProduct->agent_price) - $paymentAmount) >= 0.01) {
            return back()->withErrors(['agent_product_id' => 'Product price does not match payment amount.']);
        }

        try {
            // Create the order
            $order = Order::create([
                'user_id' => $agentProduct->agentShop->user_id, // Link to agent's user_id
                'agent_id' => $agentProduct->agentShop->user_id, // Keep agent_id for tracking
                'total' => $paymentAmount,
                'total_amount' => $paymentAmount,
                'status' => 'completed',
                'payment_method' => 'paystack',
                'payment_reference' => $request->payment_reference,
                'buyer_email' => $paymentData['customer']['email'],
                'beneficiary_number' => $request->beneficiary_number,
                'network' => $agentProduct->product->network,
                'is_guest_order' => true
            ]);

            // Attach the product to the order
            $order->products()->attach($agentProduct->product_id, [
                'quantity' => 1,
                'price' => $paymentAmount,
                'beneficiary_number' => $request->beneficiary_number,
                'product_variant_id' => $agentProduct->product_variant_id,
            ]);

            // Create commission for agent
            if ($agentProduct->commission_amount > 0) {
                $agentProduct->agentShop->agent->commissions()->create([
                    'order_id' => $order->id,
                    'product_id' => $agentProduct->product_id,
                    'product_variant_id' => $agentProduct->product_variant_id,
                    'base_price' => $agentProduct->productVariant->price ?? 0,
                    'agent_price' => $agentProduct->agent_price,
                    'commission_amount' => $agentProduct->commission_amount,
                    'quantity' => 1,
                    'status' => 'available'
                ]);
            }

            \Log::info('Order created from existing payment', [
                'order_id' => $order->id,
                'payment_reference' => $request->payment_reference,
                'shop_slug' => $slug
            ]);

            return redirect()->route('shop.order-success', [
                'slug' => $slug,
                'order' => $order->id
            ])->with('success', 'Order created successfully from your existing payment!');
            
        } catch (\Exception $e) {
            \Log::error('Failed to create order from payment', [
                'error' => $e->getMessage(),
                'payment_reference' => $request->payment_reference,
                'shop_slug' => $slug,
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors(['error' => 'Failed to create order. Please try again or contact support.']);
        }
    }

    private function handleMissingOrder(Request $request, $shop)
    {
        // Verify the payment with Paystack to check if it exists and get amount
        $paymentVerification = $this->verifyPaystackPayment($request->payment_reference);
        
        if (!$paymentVerification['status'] || $paymentVerification['data']['status'] !== 'success') {
            return back()->withErrors(['payment_reference' => 'Payment not found or verification failed.']);
        }

        $paymentData = $paymentVerification['data'];
        $paymentAmount = $paymentData['amount'] / 100; // Convert from kobo to naira
        
        \Log::info('Looking for products matching payment amount', [
            'payment_amount' => $paymentAmount,
            'shop_id' => $shop->id
        ]);
        
        // Get products from this shop that match the payment amount
        $allProducts = $this->agentService->getShopProducts($shop);
        \Log::info('All shop products', ['products' => $allProducts->toArray()]);
        
        $matchingProducts = $allProducts->filter(function($product) use ($paymentAmount) {
            $productPrice = floatval($product['agent_price']);
            $priceDifference = abs($productPrice - $paymentAmount);
            \Log::info('Price comparison', [
                'product_id' => $product['id'],
                'product_name' => $product['product']['name'],
                'product_price' => $productPrice,
                'payment_amount' => $paymentAmount,
                'difference' => $priceDifference,
                'matches' => $priceDifference < 0.01
            ]);
            return $priceDifference < 0.01;
        });

        \Log::info('Matching products found', ['count' => $matchingProducts->count()]);

        if ($matchingProducts->isEmpty()) {
            return back()->withErrors([
                'payment_reference' => 'Payment found (GHS ' . number_format($paymentAmount, 2) . ') but no products match this amount in this shop.'
            ]);
        }

        // Show product selection page
        return inertia('Shop/CreateOrderFromPayment', [
            'shop' => [
                'name' => $shop->name,
                'slug' => $shop->slug,
                'primary_color' => $shop->primary_color,
                'background_color' => $shop->background_color
            ],
            'agent' => [
                'name' => $shop->agent->name
            ],
            'payment' => [
                'reference' => $request->payment_reference,
                'amount' => $paymentAmount,
                'email' => $paymentData['customer']['email'],
                'beneficiary_number' => $request->beneficiary_number
            ],
            'products' => $matchingProducts->map(function($product) {
                return [
                    'id' => $product['id'],
                    'name' => $product['product']['name'],
                    'network' => $product['product']['network'],
                    'description' => $product['product']['description'] ?? '',
                    'agent_price' => $product['agent_price'],
                    'size' => $this->getProductSize($product['variant'])
                ];
            })->values()
        ]);
    }

    private function handleMissingOrderById(Request $request, $shop)
    {
        // Try to verify the order_id as a payment reference
        $paymentVerification = $this->verifyPaystackPayment($request->order_id);
        
        if (!$paymentVerification['status'] || $paymentVerification['data']['status'] !== 'success') {
            return back()->withErrors(['order_id' => 'Guest order not found with this reference.']);
        }

        $paymentData = $paymentVerification['data'];
        $paymentAmount = $paymentData['amount'] / 100; // Convert from kobo to naira
        
        \Log::info('Looking for products matching payment amount (by ID)', [
            'payment_amount' => $paymentAmount,
            'shop_id' => $shop->id,
            'reference' => $request->order_id
        ]);
        
        // Get products from this shop that match the payment amount
        $allProducts = $this->agentService->getShopProducts($shop);
        \Log::info('All shop products (by ID)', ['products' => $allProducts->toArray()]);
        
        $matchingProducts = $allProducts->filter(function($product) use ($paymentAmount) {
            $productPrice = floatval($product['agent_price']);
            $priceDifference = abs($productPrice - $paymentAmount);
            \Log::info('Price comparison (by ID)', [
                'product_id' => $product['id'],
                'product_name' => $product['product']['name'],
                'product_price' => $productPrice,
                'payment_amount' => $paymentAmount,
                'difference' => $priceDifference,
                'matches' => $priceDifference < 0.01
            ]);
            return $priceDifference < 0.01;
        });

        \Log::info('Matching products found (by ID)', ['count' => $matchingProducts->count()]);

        if ($matchingProducts->isEmpty()) {
            return back()->withErrors([
                'order_id' => 'Payment found (GHS ' . number_format($paymentAmount, 2) . ') but no products match this amount in this shop.'
            ]);
        }

        // Show product selection page
        return inertia('Shop/CreateOrderFromPayment', [
            'shop' => [
                'name' => $shop->name,
                'slug' => $shop->slug,
                'primary_color' => $shop->primary_color,
                'background_color' => $shop->background_color
            ],
            'agent' => [
                'name' => $shop->agent->name
            ],
            'payment' => [
                'reference' => $request->order_id,
                'amount' => $paymentAmount,
                'email' => $paymentData['customer']['email'],
                'beneficiary_number' => $request->email // Use the email from the form as beneficiary number context
            ],
            'products' => $matchingProducts->map(function($product) {
                return [
                    'id' => $product['id'],
                    'name' => $product['product']['name'],
                    'network' => $product['product']['network'],
                    'description' => $product['product']['description'] ?? '',
                    'agent_price' => $product['agent_price'],
                    'size' => $this->getProductSize($product['variant'])
                ];
            })->values()
        ]);
    }

    public function findOrder(Request $request, string $slug)
    {
        $request->validate([
            'payment_reference' => 'required|string',
            'beneficiary_number' => 'required|string'
        ]);

        $shop = AgentShop::where('slug', $slug)
            ->where('is_active', true)
            ->with('agent')
            ->firstOrFail();

        // Validate payment reference starts with "guest"
        if (!str_starts_with(strtolower($request->payment_reference), 'guest')) {
            return back()->withErrors(['payment_reference' => 'Invalid reference format. Only guest order references are allowed for tracking.']);
        }

        // Check if an order exists with this payment reference
        $existingOrder = Order::where('payment_reference', $request->payment_reference)
            ->where('is_guest_order', true)
            ->where(function($query) use ($shop) {
                $query->where('agent_id', $shop->user_id)
                      ->orWhere('user_id', $shop->user_id);
            })
            ->with('products')
            ->first();

        if ($existingOrder) {
            // Check if the beneficiary number matches
            if ($existingOrder->beneficiary_number !== $request->beneficiary_number) {
                return back()->withErrors(['beneficiary_number' => 'This payment reference has been used for an order with a different phone number.']);
            }

            // Order found - redirect to success page
            return redirect()->route('shop.order-success', [
                'slug' => $slug,
                'order' => $existingOrder->id
            ]);
        }

        // Order doesn't exist, check if payment exists and show product selection
        return $this->handleMissingOrder($request, $shop);
    }

    private function getProductSize($variant)
    {
        if (!$variant || !isset($variant['variant_attributes'])) {
            return null;
        }
        
        $attributes = $variant['variant_attributes'];
        if (!is_array($attributes)) {
            return null;
        }
        
        // Look for size-related attributes
        $sizeKeys = ['size', 'Size', 'SIZE', 'bundle', 'Bundle', 'BUNDLE'];
        foreach ($sizeKeys as $key) {
            if (isset($attributes[$key])) {
                return $attributes[$key];
            }
        }
        
        // If no specific size key, return the first attribute value
        $values = array_values($attributes);
        return !empty($values) ? $values[0] : null;
    }
}