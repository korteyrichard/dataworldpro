<?php

namespace App\Http\Controllers;

use App\Models\UserShop;
use App\Models\Order;
use App\Models\Setting;
use App\Services\AgentService;
use App\Services\OrderPusherService;
use App\Services\CodeCraftOrderPusherService;
use App\Services\JescoOrderPusherService;
use App\Services\EasyDataOrderPusherService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ShopController extends Controller
{
    public function __construct(private AgentService $agentService) {}

    public function show(string $slug)
    {
        $shop = UserShop::where('slug', $slug)
            ->where('is_active', true)
            ->with('user')
            ->firstOrFail();

        $products = $this->agentService->getShopProducts($shop);

        return inertia('Shop/Show', [
            'shop' => $shop,
            'products' => $products,
            'agent' => [
                'name' => $shop->user->name,
                'phone' => $shop->user->phone
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

        $shop = UserShop::where('slug', $slug)->firstOrFail();
        
        // Store agent_id in session for checkout
        session(['agent_id' => $shop->user_id]);

        // Use existing cart logic but with agent pricing
        // This would integrate with your existing cart system
        
        return response()->json(['message' => 'Added to cart']);
    }

    public function orderSuccess(string $slug, Order $order)
    {
        $shop = UserShop::where('slug', $slug)
            ->where('is_active', true)
            ->with('user')
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
                'name' => $shop->user->name
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

        $shop = UserShop::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Verify payment hasn't been used for an order already (system-wide check)
        $existingOrder = Order::where('payment_reference', $request->payment_reference)->first();
        if ($existingOrder) {
            // Check if the existing order belongs to this shop
            if ($existingOrder->agent_id == $shop->user_id) {
                return back()->withErrors(['payment_reference' => 'This payment has already been used for an order in this shop.']);
            } else {
                return back()->withErrors(['payment_reference' => 'This payment reference has already been used for an order with another agent. Each payment can only be used once.']);
            }
        }

        // Verify the payment with Paystack
        $paymentVerification = $this->verifyPaystackPayment($request->payment_reference);
        
        if (!$paymentVerification['status'] || $paymentVerification['data']['status'] !== 'success') {
            return back()->withErrors(['payment_reference' => 'Payment verification failed.']);
        }

        $paymentData = $paymentVerification['data'];
        $agentProduct = \App\Models\AgentProduct::with(['product', 'productVariant', 'agentShop.user'])
            ->find($request->agent_product_id);

        // Verify the product belongs to this shop
        if ($agentProduct->agentShop->slug !== $slug) {
            return back()->withErrors(['agent_product_id' => 'Invalid product selection.']);
        }

        // Verify the payment amount is reasonable for the product price (allow for Paystack charges)
        $paymentAmount = $paymentData['amount'] / 100;
        $productPrice = floatval($agentProduct->agent_price);
        $priceDifference = $paymentAmount - $productPrice;
        $maxAllowedDifference = min($productPrice * 0.05, 2.00); // 5% or GHS 2 max
        
        // Allow payment amount to be higher than product price (for Paystack charges)
        // but not significantly lower
        if ($priceDifference < -0.50 || $priceDifference > $maxAllowedDifference) {
            return back()->withErrors([
                'agent_product_id' => 'Payment amount (GHS ' . number_format($paymentAmount, 2) . 
                    ') does not reasonably match product price (GHS ' . number_format($productPrice, 2) . ').'
            ]);
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
                'is_guest_order' => true,
                'order_source' => 'shop'
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
                $agentProduct->agentShop->user->commissions()->create([
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

            // Push order to external APIs based on network and individual service settings
            $this->pushOrderToExternalApis($order);

            return redirect()->route('shop.order-success', [
                'slug' => $slug,
                'order' => $order->id
            ])->with('success', 'Order created successfully from your existing payment!');
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create order. Please try again or contact support.']);
        }
    }

    private function handleMissingOrderWithFlexiblePricing(Request $request, $shop)
    {
        // Verify the payment with Paystack to check if it exists and get amount
        $paymentVerification = $this->verifyPaystackPayment($request->payment_reference);
        
        if (!$paymentVerification['status'] || $paymentVerification['data']['status'] !== 'success') {
            return back()->withErrors(['payment_reference' => 'Payment not found or verification failed.']);
        }

        $paymentData = $paymentVerification['data'];
        $paymentAmount = $paymentData['amount'] / 100; // Convert from kobo to cedis
        
        // Get all products from this shop
        $allProducts = $this->agentService->getShopProducts($shop);
        
        // Find products with flexible pricing to account for Paystack charges
        // 1. Exact match (within 0.01 difference)
        $exactMatches = $allProducts->filter(function($product) use ($paymentAmount) {
            $productPrice = floatval($product['agent_price']);
            return abs($productPrice - $paymentAmount) < 0.01;
        });
        
        // 2. Products with price below payment amount (accounting for Paystack charges)
        // Allow up to 5% difference or GHS 2, whichever is smaller
        $flexibleMatches = $allProducts->filter(function($product) use ($paymentAmount) {
            $productPrice = floatval($product['agent_price']);
            $maxDifference = min($paymentAmount * 0.05, 2.00); // 5% or GHS 2 max
            return $productPrice <= $paymentAmount && ($paymentAmount - $productPrice) <= $maxDifference;
        });
        
        // Combine and remove duplicates
        $matchingProducts = $exactMatches->merge($flexibleMatches)->unique('id');
        
        // Sort by price difference (closest to payment amount first)
        $matchingProducts = $matchingProducts->sortBy(function($product) use ($paymentAmount) {
            return abs(floatval($product['agent_price']) - $paymentAmount);
        });

        if ($matchingProducts->isEmpty()) {
            // Show only products with prices below or equal to payment amount
            $affordableProducts = $allProducts->filter(function($product) use ($paymentAmount) {
                $productPrice = floatval($product['agent_price']);
                return $productPrice <= $paymentAmount;
            });
            
            $allProductsForDisplay = $affordableProducts->map(function($product) {
                return [
                    'id' => $product['id'],
                    'name' => $product['product']['name'],
                    'network' => $product['product']['network'],
                    'description' => $product['product']['description'] ?? '',
                    'agent_price' => $product['agent_price'],
                    'size' => $this->getProductSize($product['variant'])
                ];
            })->values();
            
            return inertia('Shop/CreateOrderFromPayment', [
                'shop' => [
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    'primary_color' => $shop->primary_color,
                    'background_color' => $shop->background_color
                ],
                'agent' => [
                    'name' => $shop->user->name
                ],
                'payment' => [
                    'reference' => $request->payment_reference,
                    'amount' => $paymentAmount,
                    'email' => $paymentData['customer']['email'],
                    'beneficiary_number' => $request->beneficiary_number
                ],
                'products' => $allProductsForDisplay,
                'message' => 'Payment found (GHS ' . number_format($paymentAmount, 2) . ') but no close price matches. Please select a product below:'
            ]);
        }

        // Show matching products
        return inertia('Shop/CreateOrderFromPayment', [
            'shop' => [
                'name' => $shop->name,
                'slug' => $shop->slug,
                'primary_color' => $shop->primary_color,
                'background_color' => $shop->background_color
            ],
            'agent' => [
                'name' => $shop->user->name
            ],
            'payment' => [
                'reference' => $request->payment_reference,
                'amount' => $paymentAmount,
                'email' => $paymentData['customer']['email'],
                'beneficiary_number' => $request->beneficiary_number
            ],
            'products' => $matchingProducts->map(function($product) use ($paymentAmount) {
                $productPrice = floatval($product['agent_price']);
                $difference = $paymentAmount - $productPrice;
                return [
                    'id' => $product['id'],
                    'name' => $product['product']['name'],
                    'network' => $product['product']['network'],
                    'description' => $product['product']['description'] ?? '',
                    'agent_price' => $product['agent_price'],
                    'size' => $this->getProductSize($product['variant']),
                    'price_difference' => $difference,
                    'is_exact_match' => abs($difference) < 0.01
                ];
            })->values()
        ]);
    }
    private function handleMissingOrder(Request $request, $shop)
    {
        // Use the new flexible pricing method
        return $this->handleMissingOrderWithFlexiblePricing($request, $shop);
    }

    private function handleMissingOrderById(Request $request, $shop)
    {
        // Try to verify the order_id as a payment reference
        $paymentVerification = $this->verifyPaystackPayment($request->order_id);
        
        if (!$paymentVerification['status'] || $paymentVerification['data']['status'] !== 'success') {
            return back()->withErrors(['order_id' => 'Guest order not found with this reference.']);
        }

        $paymentData = $paymentVerification['data'];
        $paymentAmount = $paymentData['amount'] / 100; // Convert from kobo to cedis
        
        // Get all products from this shop
        $allProducts = $this->agentService->getShopProducts($shop);
        
        // Find products with flexible pricing to account for Paystack charges
        $flexibleMatches = $allProducts->filter(function($product) use ($paymentAmount) {
            $productPrice = floatval($product['agent_price']);
            $maxDifference = min($paymentAmount * 0.05, 2.00); // 5% or GHS 2 max
            return $productPrice <= $paymentAmount && ($paymentAmount - $productPrice) <= $maxDifference;
        });
        
        // Sort by price difference (closest to payment amount first)
        $matchingProducts = $flexibleMatches->sortBy(function($product) use ($paymentAmount) {
            return abs(floatval($product['agent_price']) - $paymentAmount);
        });

        if ($matchingProducts->isEmpty()) {
            // Show only products with prices below or equal to payment amount
            $affordableProducts = $allProducts->filter(function($product) use ($paymentAmount) {
                $productPrice = floatval($product['agent_price']);
                return $productPrice <= $paymentAmount;
            });
            $matchingProducts = $affordableProducts;
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
                'name' => $shop->user->name
            ],
            'payment' => [
                'reference' => $request->order_id,
                'amount' => $paymentAmount,
                'email' => $paymentData['customer']['email'],
                'beneficiary_number' => $request->email // Use the email from the form as beneficiary number context
            ],
            'products' => $matchingProducts->map(function($product) use ($paymentAmount) {
                $productPrice = floatval($product['agent_price']);
                $difference = $paymentAmount - $productPrice;
                return [
                    'id' => $product['id'],
                    'name' => $product['product']['name'],
                    'network' => $product['product']['network'],
                    'description' => $product['product']['description'] ?? '',
                    'agent_price' => $product['agent_price'],
                    'size' => $this->getProductSize($product['variant']),
                    'price_difference' => $difference,
                    'is_reasonable_match' => $productPrice <= $paymentAmount && $difference <= min($paymentAmount * 0.05, 2.00)
                ];
            })->values()
        ]);
    }

    public function findOrder(Request $request, string $slug)
    {
        $request->validate([
            'payment_reference' => 'required|string',
            'beneficiary_number' => 'required|string|size:10'
        ]);

        $shop = UserShop::where('slug', $slug)
            ->where('is_active', true)
            ->with('user')
            ->firstOrFail();

        $paymentReference = trim($request->payment_reference);
        $beneficiaryNumber = trim($request->beneficiary_number);

        // Check if payment reference starts with "guest"
        if (!str_starts_with(strtolower($paymentReference), 'guest')) {
            return back()->withErrors([
                'payment_reference' => 'Invalid payment reference. Only guest orders can be tracked through this system.'
            ]);
        }

        // First, check if an order exists with this payment reference anywhere in the system
        $anyExistingOrder = Order::where('payment_reference', $paymentReference)->first();
        
        if ($anyExistingOrder) {
            // Check if the order belongs to this shop
            $shopOrder = Order::where('payment_reference', $paymentReference)
                ->where('is_guest_order', true)
                ->where(function($query) use ($shop) {
                    $query->where('agent_id', $shop->user_id)
                          ->orWhere('user_id', $shop->user_id);
                })
                ->with('products')
                ->first();
            
            if ($shopOrder) {
                // Order belongs to this shop - check beneficiary number
                if ($shopOrder->beneficiary_number !== $beneficiaryNumber) {
                    return back()->withErrors([
                        'beneficiary_number' => 'This payment reference was used for an order with a different phone number.'
                    ]);
                }

                // Order found and beneficiary matches - redirect to success page
                return redirect()->route('shop.order-success', [
                    'slug' => $slug,
                    'order' => $shopOrder->id
                ]);
            } else {
                // Order exists but belongs to a different shop/agent
                return back()->withErrors([
                    'payment_reference' => 'This payment reference has already been used for an order with another agent. Each payment can only be used once.'
                ]);
            }
        }
        
        // Order doesn't exist, verify payment with Paystack
        $paymentVerification = $this->verifyPaystackPayment($paymentReference);
        
        if (!$paymentVerification['status']) {
            return back()->withErrors([
                'payment_reference' => 'Payment reference not found or verification failed.'
            ]);
        }
        
        $paymentData = $paymentVerification['data'];
        
        if ($paymentData['status'] !== 'success') {
            return back()->withErrors([
                'payment_reference' => 'Payment was not successful.'
            ]);
        }
        
        // Payment exists and is successful, show product selection with flexible pricing
        return $this->handleMissingOrderWithFlexiblePricing($request, $shop);
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

    private function pushOrderToExternalApis(Order $order)
    {
        try {
            // Get service settings
            $jaybartEnabled = (bool) Setting::get('jaybart_order_pusher_enabled', 1);
            $codecraftEnabled = (bool) Setting::get('codecraft_order_pusher_enabled', 1);
            $jescoEnabled = (bool) Setting::get('jesco_order_pusher_enabled', 1);
            $easydataEnabled = (bool) Setting::get('easydata_order_pusher_enabled', 1);
            
            if (strtolower($order->network) === 'mtn') {
                if ($jaybartEnabled) {
                    $mtnOrderPusher = new OrderPusherService();
                    $mtnOrderPusher->pushOrderToApi($order);
                    \Log::info('Order pushed to Jaybart API', ['orderId' => $order->id, 'network' => $order->network]);
                }
                if ($jescoEnabled) {
                    $jescoOrderPusher = new JescoOrderPusherService();
                    $jescoOrderPusher->pushOrderToApi($order);
                    \Log::info('Order pushed to Jesco API', ['orderId' => $order->id, 'network' => $order->network]);
                }
                if ($easydataEnabled) {
                    $easydataOrderPusher = new EasyDataOrderPusherService();
                    $easydataOrderPusher->pushOrderToApi($order);
                    \Log::info('Order pushed to EasyData API', ['orderId' => $order->id, 'network' => $order->network]);
                }
                if (!$jaybartEnabled && !$jescoEnabled && !$easydataEnabled) {
                    \Log::info('All MTN order pushers disabled', ['orderId' => $order->id, 'network' => $order->network]);
                }
            } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime', 'at']) && $codecraftEnabled) {
                $codeCraftOrderPusher = new CodeCraftOrderPusherService();
                $codeCraftOrderPusher->pushOrderToApi($order);
                \Log::info('Order pushed to CodeCraft API', ['orderId' => $order->id, 'network' => $order->network]);
            } else {
                \Log::info('No enabled order pusher for network', ['orderId' => $order->id, 'network' => $order->network]);
            }
        } catch (\Exception $e) {
            $order->update(['order_pusher_status' => 'failed']);
            \Log::error('Failed to push order to external API', ['orderId' => $order->id, 'network' => $order->network, 'error' => $e->getMessage()]);
        }
    }
}