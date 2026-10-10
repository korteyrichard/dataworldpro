<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
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
use App\Models\Setting;
use App\Models\VerifiedNumber;
use App\Models\UnverifiedNumber;

class OrdersController extends Controller
{
    // Display a listing of the user's orders
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Order::with(['products' => function($query) {
            $query->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id');
        }, 'commissions'])->where('user_id', $user->id);

        // Search by order ID
        if ($request->filled('order_id')) {
            $query->where('id', (int) $request->order_id);
        }

        // Search by beneficiary number (escape LIKE wildcards in user input)
        if ($request->filled('beneficiary_number')) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $request->beneficiary_number);
            $query->where('beneficiary_number', 'like', '%' . $escaped . '%');
        }

        // Filter by status (whitelist allowed values)
        if ($request->filled('status')) {
            $allowed = ['pending', 'processing', 'completed', 'failed', 'PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'];
            if (in_array($request->status, $allowed, true)) {
                $query->where('status', $request->status);
            }
        }

        // Filter by network (whitelist allowed values)
        if ($request->filled('network')) {
            $allowedNetworks = ['mtn', 'telecel', 'ishare', 'bigtime', 'at', 'MTN', 'Telecel', 'iShare', 'Bigtime', 'AT'];
            if (in_array($request->network, $allowedNetworks, true)) {
                $query->where('network', $request->network);
            }
        }

        $orders = $query->latest()->paginate(10)->appends($request->query());
        
        // Transform orders to include variant information
        $orders->getCollection()->transform(function($order) use ($user) {
            $order->products = $order->products->map(function($product) {
                if ($product->pivot->product_variant_id) {
                    $variant = \App\Models\ProductVariant::find($product->pivot->product_variant_id);
                    if ($variant && isset($variant->variant_attributes['size'])) {
                        $product->size = strtoupper($variant->variant_attributes['size']);
                    }
                }
                return $product;
            });
            return $order;
        });

        return Inertia::render('Dashboard/orders', [
            'orders' => $orders,
            'filters' => array_map(fn($v) => is_string($v) ? e($v) : $v, $request->only(['order_id', 'beneficiary_number', 'status', 'network']))
        ]);
    }

    // Handle checkout and create separate orders for each network
    public function checkout(Request $request)
    {
        Log::info('Checkout process started.');
        $user = Auth::user();

        $cartItems = Cart::where('user_id', $user->id)->with(['product', 'productVariant'])->get();
        Log::info('Cart items fetched.', ['cartItemsCount' => $cartItems->count()]);

        if ($cartItems->isEmpty()) {
            Log::warning('Cart is empty for user.', ['userId' => $user->id]);
            return redirect()->back()->with('error', 'Cart is empty');
        }

        // Check number restriction for each cart item (MTN only)
        if (VerifiedNumber::isRestrictionEnabled()) {
            foreach ($cartItems as $item) {
                $itemNetwork = strtolower($item->network ?? ($item->product->network ?? ''));
                if ($itemNetwork !== 'mtn') {
                    continue;
                }
                if (!VerifiedNumber::isVerified($item->beneficiary_number)) {
                    UnverifiedNumber::log($item->beneficiary_number, 'verified_number', 'checkout');
                    return redirect()->back()->with('error', 'The number ' . $item->beneficiary_number . ' is not currently verified to receive orders. It has been added to the verification list and will be verified.');
                }
            }
        }

        // Check previous order restriction for each cart item (MTN only)
        if ((bool) Setting::get('previous_order_restriction_enabled', 0)) {
            foreach ($cartItems as $item) {
                $itemNetwork = strtolower($item->network ?? ($item->product->network ?? ''));
                if ($itemNetwork !== 'mtn') {
                    continue;
                }
                $hasPreviousOrder = Order::where('beneficiary_number', $item->beneficiary_number)->exists();
                if (!$hasPreviousOrder) {
                    UnverifiedNumber::log($item->beneficiary_number, 'previous_order', 'checkout');
                    return redirect()->back()->with('error', 'The number ' . $item->beneficiary_number . ' has not placed an order before and cannot receive orders at this time. It has been added to the verification list and will be verified.');
                }
            }
        }

        // Calculate total by summing the price of each cart item
        $total = $cartItems->sum(function ($item) {
            return (float) ($item->price ?? ($item->productVariant->price ?? 0));
        });
        Log::info('Total calculated.', ['total' => $total, 'walletBalance' => $user->wallet_balance]);

        // Check if user has enough wallet balance
        if ($user->wallet_balance < $total) {
            Log::warning('Insufficient wallet balance.', ['userId' => $user->id, 'walletBalance' => $user->wallet_balance, 'total' => $total]);
            return redirect()->back()->with('error', 'Insufficient wallet balance. Top up to proceed with the purchase.');
        }

        Log::info('Creating separate orders for each cart item.', ['cartItemsCount' => $cartItems->count()]);

        $createdOrders = [];
        $successMessage = '';

        DB::beginTransaction();
        Log::info('Database transaction started.');
        try {
            // Lock the user row to prevent concurrent checkouts from overdrawing balance
            $user = \App\Models\User::where('id', $user->id)->lockForUpdate()->first();

            // Re-check balance after acquiring lock
            if ($user->wallet_balance < $total) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Insufficient wallet balance. Top up to proceed with the purchase.');
            }

            // Track running balance for transaction records
            $runningBalance = (float) $user->wallet_balance;

            // Deduct wallet balance
            $user->wallet_balance = (float) bcsub((string) $user->wallet_balance, (string) $total, 2);
            $user->save();
            Log::info('Wallet balance deducted.', ['userId' => $user->id, 'newWalletBalance' => $user->wallet_balance]);

            // Create separate order for each cart item
            foreach ($cartItems as $item) {
                $itemTotal = (float) ($item->price ?? ($item->productVariant->price ?? 0));
                if (!$item->product) {
                    DB::rollBack();
                    Log::error('Product not found for cart item.', ['cartItemId' => $item->id]);
                    return redirect()->back()->with('error', 'One or more products in your cart could not be found.');
                }
                $network = $item->product->network;

                // Create the order for this item
                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'total' => $itemTotal,
                    'beneficiary_number' => $item->beneficiary_number,
                    'network' => $network,
                    'order_source' => 'dashboard'
                ]);
                Log::info('Order created for cart item.', ['orderId' => $order->id, 'network' => $network, 'total' => $itemTotal, 'beneficiaryNumber' => $item->beneficiary_number]);

                // Attach the product to the order
                $order->products()->attach($item->product_id, [
                    'quantity' => (int) ($item->quantity ?? 1),
                    'price' => $itemTotal,
                    'beneficiary_number' => $item->beneficiary_number,
                    'product_variant_id' => $item->product_variant_id,
                ]);
                Log::info('Product attached to order.', ['orderId' => $order->id, 'productId' => $item->product_id, 'beneficiaryNumber' => $item->beneficiary_number]);

                // Create a transaction record for this order
                $balanceBefore = $runningBalance;
                $runningBalance = (float) bcsub((string) $runningBalance, (string) $itemTotal, 2);
                \App\Models\Transaction::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'amount' => $itemTotal,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $runningBalance,
                    'status' => 'completed',
                    'type' => 'order',
                    'description' => 'Order placed for ' . $network . ' data/airtime.',
                ]);
                Log::info('Transaction created for order.', ['orderId' => $order->id, 'network' => $network]);

                $createdOrders[] = $order;
            }

            // Clear user's cart
            Cart::where('user_id', $user->id)->delete();
            Log::info('Cart cleared.', ['userId' => $user->id]);

            DB::commit();
            Log::info('Database transaction committed.');

            // Push orders to external APIs based on network and individual service settings
            $jaybartEnabled = (bool) Setting::get('jaybart_order_pusher_enabled', 1);
            $codecraftEnabled = (bool) Setting::get('codecraft_order_pusher_enabled', 1);
            $codecraftMtnEnabled = (bool) Setting::get('codecraft_mtn_order_pusher_enabled', 1);
            $jescoEnabled = (bool) Setting::get('jesco_order_pusher_enabled', 1);
            $easydataEnabled = (bool) Setting::get('easydata_order_pusher_enabled', 1);
            $skanka5Enabled = (bool) Setting::get('skanka5_order_pusher_enabled', 1);
            $skanka5TelecelEnabled = (bool) Setting::get('skanka5_telecel_order_pusher_enabled', 1);
            $dataflowEnabled = (bool) Setting::get('dataflow_order_pusher_enabled', 0);
            $masterNetEnabled = (bool) Setting::get('successbizhub_order_pusher_enabled', 0);
            $masterNetGhEnabled = (bool) Setting::get('masternetgh_order_pusher_enabled', 0);
            $bundlePortalMtnEnabled = (bool) Setting::get('bundleportal_mtn_api_enabled', 0);
            $bundlePortalEnabled = (bool) Setting::get('bundleportal_api_enabled', 0);
            
            foreach ($createdOrders as $order) {
                try {
                    if (strtolower($order->network) === 'mtn') {
                        if ($bundlePortalMtnEnabled) {
                            (new BundlePortalMtnOrderPusherService())->pushOrderToApi($order);
                            Log::info('Order pushed to BundlePortal MTN API', ['orderId' => $order->id]);
                        } elseif ($codecraftMtnEnabled) {
                            (new CodeCraftMtnOrderPusherService())->pushOrderToApi($order);
                            Log::info('Order pushed to CodeCraft MTN API', ['orderId' => $order->id, 'network' => $order->network]);
                        } elseif ($dataflowEnabled) {
                            (new DataflowOrderPusherService())->pushOrderToApi($order);
                            Log::info('Order pushed to Dataflow API', ['orderId' => $order->id, 'network' => $order->network]);
                        } elseif ($easydataEnabled) {
                            (new EasyDataOrderPusherService())->pushOrderToApi($order);
                            Log::info('Order pushed to EasyData API', ['orderId' => $order->id, 'network' => $order->network]);
                        } elseif ($skanka5Enabled) {
                            (new Skanka5OrderPusherService())->pushOrderToApi($order);
                            Log::info('Order pushed to Skanka5 API', ['orderId' => $order->id, 'network' => $order->network]);
                        } elseif ($jaybartEnabled) {
                            (new OrderPusherService())->pushOrderToApi($order);
                            Log::info('Order pushed to Jaybart API', ['orderId' => $order->id]);
                        }
                        if ($masterNetEnabled) {
                            (new MasterNetOrderPusherService())->pushOrderToApi($order);
                            Log::info('Order pushed to MasterNet DataHub API', ['orderId' => $order->id]);
                        }
                        if ($masterNetGhEnabled) {
                            (new MasterNetGhOrderPusherService())->pushOrderToApi($order);
                            Log::info('Order pushed to MasterNetGh API', ['orderId' => $order->id]);
                        }
                        if ($jescoEnabled) {
                            (new JescoOrderPusherService())->pushOrderToApi($order);
                        }
                    } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime', 'at'])) {
                        if ($bundlePortalEnabled) {
                            (new BundlePortalOrderPusherService())->pushOrderToApi($order);
                            Log::info('Order pushed to BundlePortal API', ['orderId' => $order->id]);
                        } else {
                            $codeCraftOrderPusher = new CodeCraftOrderPusherService();
                            $codeCraftOrderPusher->pushOrderToApi($order);
                            Log::info('Order pushed to CodeCraft API (fallback)', ['orderId' => $order->id]);
                        }
                        if (strtolower($order->network) === 'telecel' && $skanka5TelecelEnabled) {
                            $skanka5TelecelOrderPusher = new Skanka5OrderPusherTelecelService();
                            $skanka5TelecelOrderPusher->pushOrderToApi($order);
                        }
                    } else {
                        Log::info('No enabled order pusher for network', ['orderId' => $order->id, 'network' => $order->network]);
                    }
                } catch (\Exception $e) {
                    $order->update(['order_pusher_status' => 'failed']);
                    Log::error('Failed to push order to external API', ['orderId' => $order->id, 'network' => $order->network, 'error' => $e->getMessage()]);
                }
            }

            $orderCount = count($createdOrders);
            $successMessage = $orderCount === 1 
                ? 'Order placed successfully!' 
                : "$orderCount orders placed successfully!";

            return redirect()->route('dashboard.orders')->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed during transaction.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Checkout failed. Please try again.');
        }
    }
}