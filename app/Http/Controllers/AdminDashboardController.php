<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\SmsService;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index()
    {
        $users = User::all();
        $products = Product::with('variants')->get();
        $orders = Order::with(['products' => function($query) {
            $query->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id');
        }])->get();
        
        // Transform orders to include variant information
        $orders = $orders->map(function($order) {
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
        $transactions = Transaction::all();

        $today = now()->today();
        $todayUsers = User::whereDate('created_at', $today)->get();
        $todayOrders = Order::with(['products' => function($query) {
            $query->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id');
        }])->whereDate('created_at', $today)->get();
        
        // Transform today's orders to include variant information
        $todayOrders = $todayOrders->map(function($order) {
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
        $todayTransactions = Transaction::whereDate('created_at', $today)->get();

        return Inertia::render('Admin/Dashboard', [
            'users' => $users,
            'products' => $products,
            'orders' => $orders,
            'transactions' => $transactions,
            'todayUsers' => $todayUsers,
            'todayOrders' => $todayOrders,
            'todayTransactions' => $todayTransactions,
            'orderPusherEnabled' => (bool) Setting::get('order_pusher_enabled', 1),
        ]);
    }

    /**
     * Display the admin users page.
     */
    public function users(Request $request)
    {
        $users = User::query();

        // Search by email
        if ($request->has('email') && $request->input('email') !== '') {
            $users->where('email', 'like', '%' . $request->input('email') . '%');
        }

        // Filter by role
        if ($request->has('role') && $request->input('role') !== '') {
            $users->where('role', $request->input('role'));
        }

        // Get user statistics
        $totalUsers = User::count();
        $customerCount = User::where('role', 'customer')->count();
        $agentCount = User::where('role', 'agent')->count();
        $adminCount = User::where('role', 'admin')->count();
        $totalWalletBalance = User::sum('wallet_balance');

        return Inertia::render('Admin/Users', [
            'users' => $users->select('id', 'name', 'email', 'role', 'wallet_balance', 'created_at', 'updated_at')->paginate(15),
            'filterEmail' => $request->input('email', ''),
            'filterRole' => $request->input('role', ''),
            'userStats' => [
                'total' => $totalUsers,
                'customers' => $customerCount,
                'agents' => $agentCount,
                'admins' => $adminCount,
                'totalWalletBalance' => $totalWalletBalance,
            ],
        ]);
    }

    /**
     * Display the admin products page.
     */
    public function products(Request $request)
    {
        $products = Product::with('variants');

        if ($request->has('network') && $request->input('network') !== '') {
            $products->where('network', 'like', '%' . $request->input('network') . '%');
        }

        $productsData = $products->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'network' => $product->network,
                'product_type' => $product->product_type,
                'expiry' => $product->expiry,
                'has_variants' => $product->has_variants,
                'variants' => $product->variants,
                'price_range' => $product->getPriceRange(),
            ];
        });

        return Inertia::render('Admin/Products', [
            'products' => $productsData,
            'filterNetwork' => $request->input('network', ''),
        ]);
    }

    /**
     * Display the admin orders page.
     */
    public function orders(Request $request)
    {
        $orders = Order::with(['products' => function($query) {
            $query->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id');
        }, 'user'])->latest();

        if ($request->has('network') && $request->input('network') !== '') {
            $orders->where('network', 'like', '%' . $request->input('network') . '%');
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $orders->where('status', $request->input('status'));
        }

        $paginatedOrders = $orders->paginate(10);
        
        // Transform orders to include variant information
        $paginatedOrders->getCollection()->transform(function($order) {
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

        return Inertia::render('Admin/Orders', [
            'orders' => $paginatedOrders,
            'filterNetwork' => $request->input('network', ''),
            'filterStatus' => $request->input('status', ''),
        ]);
    }

    /**
     * Delete an order.
     */
    public function deleteOrder(Order $order)
    {
        $order->delete();
        return redirect()->back()->with('success', 'Order deleted successfully.');
    }

    /**
     * Update an order's status.
     */
    public function updateOrderStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|string|in:pending,processing,completed,cancelled',
        ]);

        $oldStatus = $order->status;
        $order->update(['status' => $request->status]);

        // Handle automatic refund when order is cancelled
        if ($request->status === 'cancelled' && $oldStatus !== 'cancelled') {
            $user = $order->user;
            $refundAmount = $order->total;
            
            // Add refund to user's wallet
            $user->increment('wallet_balance', $refundAmount);
            
            // Create refund transaction record
            Transaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'amount' => $refundAmount,
                'status' => 'completed',
                'type' => 'refund',
                'description' => "Refund for cancelled order #{$order->id}",
            ]);
            
            // Send SMS notification for refund
            if ($user->phone) {
                $smsService = new SmsService();
                $message = "Your order #{$order->id} has been cancelled and GHS " . number_format($refundAmount, 2) . " has been refunded to your wallet.";
                $smsService->sendSms($user->phone, $message);
            }
        }

        // Send SMS if status changed to completed
        if ($request->status === 'completed' && $oldStatus !== 'completed' && $order->user->phone) {
            $smsService = new SmsService();
            $message = "Your order #{$order->id} has been completed. Total: GHS " . number_format($order->total, 2);
            $smsService->sendSms($order->user->phone, $message);
        }

        return redirect()->back()->with('success', 'Order status updated successfully.');
    }

    /**
     * Bulk update order statuses.
     */
    public function bulkUpdateOrderStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'status' => 'required|string|in:pending,processing,completed,cancelled',
        ]);

        // Get orders before update for SMS notifications
        $orders = Order::with('user')->whereIn('id', $request->order_ids)->get();
        
        $updatedCount = Order::whereIn('id', $request->order_ids)
            ->update(['status' => $request->status]);

        // Handle automatic refunds when orders are cancelled
        if ($request->status === 'cancelled') {
            $smsService = new SmsService();
            foreach ($orders as $order) {
                if ($order->status !== 'cancelled') {
                    $user = $order->user;
                    $refundAmount = $order->total;
                    
                    // Add refund to user's wallet
                    $user->increment('wallet_balance', $refundAmount);
                    
                    // Create refund transaction record
                    Transaction::create([
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'amount' => $refundAmount,
                        'status' => 'completed',
                        'type' => 'refund',
                        'description' => "Refund for cancelled order #{$order->id}",
                    ]);
                    
                    // Send SMS notification for refund
                    if ($user->phone) {
                        $message = "Your order #{$order->id} has been cancelled and GHS " . number_format($refundAmount, 2) . " has been refunded to your wallet.";
                        $smsService->sendSms($user->phone, $message);
                    }
                }
            }
        }

        // Send SMS notifications if status changed to completed
        if ($request->status === 'completed') {
            $smsService = new SmsService();
            foreach ($orders as $order) {
                if ($order->status !== 'completed' && $order->user->phone) {
                    $message = "Your order #{$order->id} has been completed. Total: GHS " . number_format($order->total, 2);
                    $smsService->sendSms($order->user->phone, $message);
                }
            }
        }

        return redirect()->back()->with('success', "Updated {$updatedCount} order(s) successfully.");
    }

    /**
     * Display the admin transactions page.
     */
    public function transactions(Request $request)
    {
        $transactions = Transaction::with('user', 'order.user')->latest();

        if ($request->has('type') && $request->input('type') !== '') {
            $transactions->where('type', $request->input('type'));
        }

        return Inertia::render('Admin/Transactions', [
            'transactions' => $transactions->paginate(10),
            'filterType' => $request->input('type', ''),
        ]);
    }

    /**
     * Store a new user.
     */
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:customer,agent,admin',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('admin.users');
    }

    /**
     * Update the user's role.
     */
    public function updateUserRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|string|in:customer,agent,admin',
        ]);

        $user->update([
            'role' => $request->role,
        ]);

        return redirect()->route('admin.users');
    }

    /**
     * Delete the user.
     */
    public function deleteUser(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users');
    }

    /**
     * Credit user's wallet.
     */
    public function creditWallet(Request $request, User $user)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user->increment('wallet_balance', $request->amount);

        return redirect()->route('admin.users')->with('success', 'Wallet credited successfully.');
    }

    /**
     * Debit user's wallet.
     */
    public function debitWallet(Request $request, User $user)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($user->wallet_balance < $request->amount) {
            return redirect()->route('admin.users')->with('error', 'Insufficient wallet balance.');
        }

        $user->decrement('wallet_balance', $request->amount);

        return redirect()->route('admin.users')->with('success', 'Wallet debited successfully.');
    }

    /**
     * Store a new product.
     */
    public function storeProduct(Request $request)
    {
        \Log::info('=== STORE PRODUCT REQUEST START ===');
        \Log::info('Request Method:', [$request->method()]);
        \Log::info('Request URL:', [$request->url()]);
        \Log::info('Request Headers:', $request->headers->all());
        \Log::info('Store Product Request Data:', $request->all());
        \Log::info('Request Input Count:', [count($request->all())]);
        \Log::info('complete request',[$request->all()]);
        
        try {
            \Log::info('Starting validation...');
            $request->validate([
                'name' => 'required|string|max:255',
                'network' => 'required|in:MTN,Telecel,Ishare,Bigtime',
                'description' => 'required|string|max:255',
                'expiry' => 'required|in:non expiry,30 days,24 hours',
                'product_type' => 'required|in:agent_product,customer_product',
                'variants' => 'required|array|min:1',
                'variants.*.price' => 'required|numeric|min:0',
                'variants.*.quantity' => 'required|string',
                'variants.*.status' => 'required|in:IN STOCK,OUT OF STOCK',
            ]);
            \Log::info('Validation passed successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed:', $e->errors());
            throw $e;
        }

        try {
            \Log::info('Creating product with data:', [
                'name' => $request->name,
                'network' => $request->network,
                'description' => $request->description,
                'expiry' => $request->expiry,
                'product_type' => $request->product_type,
                'has_variants' => count($request->variants) > 1,
            ]);
            
            $product = Product::create([
                'name' => $request->name,
                'network' => $request->network,
                'description' => $request->description,
                'expiry' => $request->expiry,
                'product_type' => $request->product_type,
                'has_variants' => count($request->variants) > 1,
            ]);

            \Log::info('Product created with ID: ' . $product->id);

            foreach ($request->variants as $index => $variantData) {
                \Log::info('Creating variant ' . ($index + 1) . ':', $variantData);
                
                ProductVariant::create([
                    'product_id' => $product->id,
                    'price' => $variantData['price'],
                    'quantity' => $variantData['quantity'],
                    'status' => $variantData['status'],
                    'variant_attributes' => ['size' => $variantData['quantity']],
                ]);
            }

            \Log::info('Product and variants created successfully');
            return redirect()->route('admin.products')->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            \Log::error('=== PRODUCT CREATION FAILED ===');
            \Log::error('Error message: ' . $e->getMessage());
            \Log::error('Error file: ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Log::error('Request data at time of error:', $request->all());
            return redirect()->back()->withErrors(['error' => 'Failed to create product: ' . $e->getMessage()])->withInput();
        }
        
        \Log::info('=== STORE PRODUCT REQUEST END ===');
    }

    /**
     * Update a product.
     */
    public function updateProduct(Request $request, Product $product)
    {
        \Log::info('Update Product Request Data:', $request->all());
        \Log::info('Updating product ID: ' . $product->id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'network' => 'required|in:MTN,Telecel,Ishare,Bigtime',
            'description' => 'required|string|max:255',
            'expiry' => 'required|in:non expiry,30 days,24 hours',
            'product_type' => 'required|in:agent_product,customer_product',
            'variants' => 'required|array|min:1',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.quantity' => 'required|string',
            'variants.*.status' => 'required|in:IN STOCK,OUT OF STOCK',
        ]);

        try {
            \DB::transaction(function () use ($request, $product) {
                $product->update([
                    'name' => $request->name,
                    'network' => $request->network,
                    'description' => $request->description,
                    'expiry' => $request->expiry,
                    'product_type' => $request->product_type,
                    'has_variants' => count($request->variants) > 1,
                ]);

                $existingVariants = $product->variants;
                $requestVariants = collect($request->variants);

                // Update existing variants or create new ones
                $requestVariants->each(function ($variantData, $index) use ($product, $existingVariants) {
                    if (isset($existingVariants[$index])) {
                        // Update existing variant
                        $existingVariants[$index]->update([
                            'price' => $variantData['price'],
                            'quantity' => $variantData['quantity'],
                            'status' => $variantData['status'],
                            'variant_attributes' => ['size' => $variantData['quantity']],
                        ]);
                    } else {
                        // Create new variant
                        $product->variants()->create([
                            'price' => $variantData['price'],
                            'quantity' => $variantData['quantity'],
                            'status' => $variantData['status'],
                            'variant_attributes' => ['size' => $variantData['quantity']],
                        ]);
                    }
                });

                // Delete excess variants if any
                if ($existingVariants->count() > $requestVariants->count()) {
                    $variantsToDelete = $existingVariants->slice($requestVariants->count());
                    foreach ($variantsToDelete as $variant) {
                        $variant->delete();
                    }
                }
            });

            return redirect()->route('admin.products')->with('success', 'Product updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update product: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update product: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Delete a product.
     */
    public function deleteProduct(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products');
    }

    /**
     * Display user transaction history.
     */
    public function userTransactions(User $user)
    {
        $transactions = Transaction::where('user_id', $user->id)
            ->with('order')
            ->latest()
            ->get();

        return Inertia::render('Admin/UserTransactions', [
            'user' => $user,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Export selected orders to CSV.
     */
    public function exportOrders(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
        ]);

        $orders = Order::with(['products' => function($query) {
            $query->withPivot('quantity', 'beneficiary_number', 'product_variant_id');
        }])->whereIn('id', $request->order_ids)->get();

        $filename = 'orders_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($orders) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Number', 'Volume']);
            
            foreach ($orders as $order) {
                foreach ($order->products as $product) {
                    fputcsv($file, [
                        $product->pivot->beneficiary_number ?? 'N/A',
                        $product->pivot->quantity ?? 0
                    ]);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Display AFA orders page.
     */
    public function afaOrders()
    {
        $afaOrders = \App\Models\AFAOrders::with(['afaproduct', 'user'])->latest()->get();
        
        return Inertia::render('Admin/AFAOrders', [
            'afaOrders' => $afaOrders
        ]);
    }

    /**
     * Update AFA order status.
     */
    public function updateAfaOrderStatus(Request $request, \App\Models\AFAOrders $order)
    {
        $request->validate([
            'status' => 'required|string|in:PENDING,COMPLETED,CANCELLED',
        ]);

        $order->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'AFA order status updated successfully.');
    }

    /**
     * Toggle order pusher functionality.
     */
    public function toggleOrderPusher(Request $request)
    {
        $enabled = $request->input('enabled', false);
        Setting::set('order_pusher_enabled', $enabled ? '1' : '0');
        
        $status = $enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "Order pusher {$status} successfully.");
    }
}