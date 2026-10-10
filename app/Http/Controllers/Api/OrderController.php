<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use App\Services\OrderPusherService;
use App\Services\CodeCraftOrderPusherService;
use App\Services\JescoOrderPusherService;
use App\Services\EasyDataOrderPusherService;
use App\Services\Skanka5OrderPusherService;
use App\Services\Skanka5OrderPusherTelecelService;
use App\Services\CodeCraftMtnOrderPusherService;
use App\Services\DataflowOrderPusherService;
use App\Services\MasterNetOrderPusherService;
use App\Services\MasterNetGhOrderPusherService;
use App\Services\BundlePortalMtnOrderPusherService;
use App\Services\BundlePortalOrderPusherService;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Models\VerifiedNumber;
use App\Models\UnverifiedNumber;
use App\Services\PreviousOrderRestrictionService;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = auth()->user()->orders()->with('products')->latest()->get();
        return response()->json($orders);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request)
    {
        $user = auth()->user();

        // Determine product type based on user role
        $productType = $user->role === 'customer' ? 'customer_product' : 'agent_product';

        $product = Product::where('id', $request->network_id)
            ->where('product_type', $productType)
            ->first();
            
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // MTN-only restrictions
        if (strtolower($product->network) === 'mtn') {
            if (!VerifiedNumber::isAllowed($request->beneficiary_number)) {
                UnverifiedNumber::log($request->beneficiary_number, 'verified_number', 'api');
                return response()->json(['error' => 'This number is not allowed to receive orders at this time.'], 403);
            }
            if (!PreviousOrderRestrictionService::isAllowed($request->beneficiary_number, $product->network)) {
                UnverifiedNumber::log($request->beneficiary_number, 'previous_order', 'api');
                return response()->json(['error' => 'Order can not be placed for this number.'], 403);
            }
        }

        $variant = ProductVariant::where('product_id', $product->id)
            ->whereJsonContains('variant_attributes->size', $request->size)
            ->first();
            
        if (!$variant) {
            return response()->json(['error' => 'Size variant not available'], 404);
        }

        // Check if variant is available (status check instead of quantity)
        if ($variant->status !== 'IN STOCK') {
            return response()->json(['error' => 'Product variant is out of stock'], 400);
        }

        try {
            $order = DB::transaction(function() use ($request, $product, $variant) {
                // Lock user row to prevent concurrent overdraw
                $lockedUser = \App\Models\User::where('id', auth()->id())->lockForUpdate()->first();

                if ($lockedUser->wallet_balance < $variant->price) {
                    throw new \Exception('Insufficient wallet balance');
                }

                // Check for existing orders with lock to prevent duplicate orders
                $existingOrder = Order::where('beneficiary_number', $request->beneficiary_number)
                    ->whereIn('status', ['processing', 'pending'])
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder) {
                    throw new \Exception('An order is already being processed for this beneficiary number. Please wait for it to complete before placing a new order.');
                }

                $balanceBefore = $lockedUser->wallet_balance;
                $lockedUser->decrement('wallet_balance', $variant->price);

                $order = Order::create([
                    'user_id' => $lockedUser->id,
                    'total' => $variant->price,
                    'beneficiary_number' => $request->beneficiary_number,
                    'network' => $product->network,
                    'status' => 'pending',
                    'order_source' => 'api'
                ]);

                $order->products()->attach($product->id, [
                    'quantity' => 1,
                    'price' => $variant->price,
                    'beneficiary_number' => $request->beneficiary_number,
                    'product_variant_id' => $variant->id
                ]);

                \App\Models\Transaction::create([
                    'user_id' => $lockedUser->id,
                    'order_id' => $order->id,
                    'amount' => $variant->price,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $lockedUser->wallet_balance,
                    'status' => 'completed',
                    'type' => 'order',
                    'description' => 'API order placed for ' . $product->network . ' data/airtime.',
                ]);

                return $order;
            });
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
        
        // Push order to external API based on network (if enabled)
        try {
            if (strtolower($order->network) === 'mtn') {
                if (Setting::get('bundleportal_mtn_api_enabled', 0)) {
                    (new BundlePortalMtnOrderPusherService())->pushOrderToApi($order);
                } elseif (Setting::get('codecraft_mtn_order_pusher_enabled', 1)) {
                    (new CodeCraftMtnOrderPusherService())->pushOrderToApi($order);
                } elseif (Setting::get('dataflow_order_pusher_enabled', 0)) {
                    (new DataflowOrderPusherService())->pushOrderToApi($order);
                } elseif (Setting::get('easydata_order_pusher_enabled', 1)) {
                    (new EasyDataOrderPusherService())->pushOrderToApi($order);
                } elseif (Setting::get('skanka5_order_pusher_enabled', 1)) {
                    (new Skanka5OrderPusherService())->pushOrderToApi($order);
                } elseif (Setting::get('jaybart_order_pusher_enabled', 1)) {
                    (new OrderPusherService())->pushOrderToApi($order);
                }
                if (Setting::get('successbizhub_order_pusher_enabled', 0)) {
                    (new MasterNetOrderPusherService())->pushOrderToApi($order);
                }
                if (Setting::get('masternetgh_order_pusher_enabled', 0)) {
                    (new MasterNetGhOrderPusherService())->pushOrderToApi($order);
                }
                if (Setting::get('jesco_order_pusher_enabled', 1)) {
                    (new JescoOrderPusherService())->pushOrderToApi($order);
                }
            } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime'])) {
                if (Setting::get('bundleportal_api_enabled', 0)) {
                    (new BundlePortalOrderPusherService())->pushOrderToApi($order);
                } else {
                    (new CodeCraftOrderPusherService())->pushOrderToApi($order);
                }
                if (strtolower($order->network) === 'telecel' && Setting::get('skanka5_telecel_order_pusher_enabled', 1)) {
                    (new Skanka5OrderPusherTelecelService())->pushOrderToApi($order);
                }
            } else {
                Log::info('Order pusher disabled for network - skipping API call', ['orderId' => $order->id, 'network' => $order->network]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to push order to external API', ['orderId' => $order->id, 'network' => $order->network, 'error' => $e->getMessage()]);
        }

        // Load the order with its products and user for the response
        $order->load(['products' => function($query) {
            $query->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id');
        }, 'user']);
        
        return response()->json([
            'message' => 'Order created successfully',
            'order' => [
                'reference_id' => $order->id,
                'total' => $order->total,
                'status' => $order->status,
                'network' => $order->network,
                'beneficiary_number' => $order->beneficiary_number,
                'created_at' => $order->created_at,
                'user' => [
                    'name' => $order->user->name,
                    'email' => $order->user->email
                ],
                'products' => $order->products->map(function($product) {
                    return [
                        'name' => $product->name,
                        'quantity' => $product->pivot->quantity,
                        'price' => $product->pivot->price
                    ];
                })
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
