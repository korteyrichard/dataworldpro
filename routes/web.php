<?php

use App\Http\Controllers\BecomeAgentController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\JoinUsController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AFAController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\AdminController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/become_an_agent', function () {
        $agentFee = \App\Models\Setting::get('agent_upgrade_fee', 30);
        return Inertia::render('become_an_agent', [
            'agentFee' => (float) $agentFee
        ]);
    })->name('become_an_agent');

Route::middleware(['auth'])->group(function () {
    Route::post('/become_an_agent', [BecomeAgentController::class, 'update'])->name('become_an_agent.update');
});
Route::get('/agent/callback', [BecomeAgentController::class, 'handleAgentCallback'])->name('agent.callback');

// Upgrade to agent route - accessible to both guests and authenticated users
Route::get('/upgrade-to-agent', [AgentController::class, 'showUpgrade'])->name('upgrade.agent.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/wallet', [WalletController::class, 'index'])->name('dashboard.wallet');
    Route::get('/dashboard/joinUs', [JoinUsController::class, 'index'])->name('dashboard.joinUs');
    Route::get('/dashboard/orders', [OrdersController::class, 'index'])->name('dashboard.orders');
    Route::get('/dashboard/transactions', [TransactionsController::class, 'index'])->name('dashboard.transactions');
    Route::get('/dashboard/afa-registration', [AFAController::class, 'index'])->name('dashboard.afa');
    Route::post('/dashboard/afa-registration', [AFAController::class, 'store'])->name('dashboard.afa.store');
    Route::get('/dashboard/afa-orders', [AFAController::class, 'afaOrders'])->name('dashboard.afa.orders');
    Route::get('/dashboard/api-docs', [ApiDocsController::class, 'index'])->name('dashboard.api-docs');
    Route::get('/dashboard/terms', [TermsController::class, 'index'])->name('dashboard.terms');

    // Cart routes
    Route::post('/add-to-cart', [CartController::class, 'store'])->name('add.to.cart');
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->name('remove.from.cart');
    Route::post('/process-excel-to-cart', [CartController::class, 'processExcelToCart']);
    Route::post('/process-bulk-to-cart', [CartController::class, 'processBulkToCart']);

    // Wallet balance route
    Route::post('/dashboard/wallet/add', [DashboardController::class, 'addToWallet'])->name('dashboard.wallet.add');
    Route::get('/wallet/callback', [DashboardController::class, 'handleWalletCallback'])->name('wallet.callback');
    Route::post('/dashboard/wallet/verify-payment', [WalletController::class, 'verifyPayment'])->name('dashboard.wallet.verify');
    
    // Bundle sizes API
    Route::get('/api/bundle-sizes', [DashboardController::class, 'getBundleSizes'])->name('api.bundle-sizes');

    // Business features - accessible to all authenticated users
    Route::middleware(['auth', 'role:agent'])->prefix('agent')->name('agent.')->group(function () {
        Route::get('/dashboard', [AgentController::class, 'dashboard'])->name('dashboard');
        Route::get('/commissions', [AgentController::class, 'commissions'])->name('commissions');
        Route::get('/referrals', [AgentController::class, 'referrals'])->name('referrals');
        Route::get('/withdrawals', [AgentController::class, 'withdrawals'])->name('withdrawals');
        Route::get('/shop', [AgentController::class, 'shop'])->name('shop');
        Route::post('/shop', [AgentController::class, 'createShop'])->name('shop.create');
        Route::put('/shop', [AgentController::class, 'updateShop'])->name('shop.update');
        Route::post('/products', [AgentController::class, 'addProduct'])->name('products.add');
        Route::delete('/products/{agentProduct}', [AgentController::class, 'removeProduct'])->name('products.remove');
        Route::post('/withdrawals', [AgentController::class, 'requestWithdrawal'])->name('withdrawals.request');
    });
    
    // Upgrade to agent routes (available to authenticated users only)
    Route::post('/upgrade-to-agent', [AgentController::class, 'upgradeToAgent'])->name('upgrade.agent');
});

// Checkout route
Route::middleware(['auth'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/place_order', [OrdersController::class, 'checkout'])->name('checkout.process');
});

// Admin routes - This is the correct group with role middleware
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('users', [\App\Http\Controllers\AdminDashboardController::class, 'users'])->name('users');
    Route::post('users', [\App\Http\Controllers\AdminDashboardController::class, 'storeUser'])->name('users.store');
    Route::put('users/{user}', [\App\Http\Controllers\AdminDashboardController::class, 'updateUserRole'])->name('users.updateRole');
    Route::delete('users/{user}', [\App\Http\Controllers\AdminDashboardController::class, 'deleteUser'])->name('users.delete');
    Route::post('users/{user}/credit', [\App\Http\Controllers\AdminDashboardController::class, 'creditWallet'])->name('users.credit');
    Route::post('users/{user}/debit', [\App\Http\Controllers\AdminDashboardController::class, 'debitWallet'])->name('users.debit');
    Route::get('products', [\App\Http\Controllers\AdminDashboardController::class, 'products'])->name('products');
    Route::post('products', [\App\Http\Controllers\AdminDashboardController::class, 'storeProduct'])->name('products.store');
    Route::put('products/{product}', [\App\Http\Controllers\AdminDashboardController::class, 'updateProduct'])->name('products.update');
    Route::delete('products/{product}', [\App\Http\Controllers\AdminDashboardController::class, 'deleteProduct'])->name('products.delete');
    Route::get('variations', [\App\Http\Controllers\Admin\VariationAttributeController::class, 'index'])->name('variations');
    Route::post('variation-attributes', [\App\Http\Controllers\Admin\VariationAttributeController::class, 'store'])->name('variation-attributes.store');
    Route::put('variation-attributes/{variationAttribute}', [\App\Http\Controllers\Admin\VariationAttributeController::class, 'update'])->name('variation-attributes.update');
    Route::delete('variation-attributes/{variationAttribute}', [\App\Http\Controllers\Admin\VariationAttributeController::class, 'destroy'])->name('variation-attributes.delete');
    Route::get('orders', [\App\Http\Controllers\AdminDashboardController::class, 'orders'])->name('orders');
    Route::delete('orders/{order}', [\App\Http\Controllers\AdminDashboardController::class, 'deleteOrder'])->name('orders.delete');
    Route::put('orders/{order}/status', [\App\Http\Controllers\AdminDashboardController::class, 'updateOrderStatus'])->name('orders.updateStatus');
    Route::put('orders/bulk-status', [\App\Http\Controllers\AdminDashboardController::class, 'bulkUpdateOrderStatus'])->name('orders.bulkUpdateStatus');
    Route::get('transactions', [\App\Http\Controllers\AdminDashboardController::class, 'transactions'])->name('transactions');
    Route::get('users/{user}/transactions', [\App\Http\Controllers\AdminDashboardController::class, 'userTransactions'])->name('users.transactions');
    Route::post('orders/export', [\App\Http\Controllers\AdminDashboardController::class, 'exportOrders'])->name('orders.export');
    Route::get('afa-orders', [\App\Http\Controllers\AdminDashboardController::class, 'afaOrders'])->name('afa-orders');
    Route::put('afa-orders/{order}/status', [\App\Http\Controllers\AdminDashboardController::class, 'updateAfaOrderStatus'])->name('afa.orders.updateStatus');
    Route::get('afa-products', [\App\Http\Controllers\AdminDashboardController::class, 'afaProducts'])->name('afa-products');
    Route::post('afa-products', [\App\Http\Controllers\AdminDashboardController::class, 'storeAfaProduct'])->name('afa-products.store');
    Route::put('afa-products/{afaProduct}', [\App\Http\Controllers\AdminDashboardController::class, 'updateAfaProduct'])->name('afa-products.update');
    Route::delete('afa-products/{afaProduct}', [\App\Http\Controllers\AdminDashboardController::class, 'deleteAfaProduct'])->name('afa-products.delete');
    Route::post('toggle-jaybart-order-pusher', [\App\Http\Controllers\AdminDashboardController::class, 'toggleJaybartOrderPusher'])->name('toggle.jaybart.order.pusher');
    Route::post('toggle-codecraft-order-pusher', [\App\Http\Controllers\AdminDashboardController::class, 'toggleCodecraftOrderPusher'])->name('toggle.codecraft.order.pusher');
    Route::post('toggle-jesco-order-pusher', [\App\Http\Controllers\AdminDashboardController::class, 'toggleJescoOrderPusher'])->name('toggle.jesco.order.pusher');
    Route::post('toggle-easydata-order-pusher', [\App\Http\Controllers\AdminDashboardController::class, 'toggleEasydataOrderPusher'])->name('toggle.easydata.order.pusher');
    
    // Alert routes
    Route::resource('alerts', \App\Http\Controllers\Admin\AlertController::class);
    
    // Agent system admin routes
    Route::get('agents', [AdminController::class, 'agents'])->name('agents');
    Route::get('commissions', [AdminController::class, 'commissions'])->name('commissions');
    Route::get('withdrawals', [AdminController::class, 'withdrawals'])->name('withdrawals');
    Route::post('withdrawals/{withdrawal}/process', [AdminController::class, 'processWithdrawal'])->name('withdrawals.process');
    Route::get('settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('settings', [AdminController::class, 'updateSettings'])->name('settings.update');
});

// Paystack payment routes
Route::get('/payment', function () {
    return view('payment');
})->name('payment');
Route::post('/payment/initialize', [PaymentController::class, 'initializePayment'])->name('payment.initialize');
Route::get('/payment/callback', [PaymentController::class, 'handleCallback'])->name('payment.callback');
Route::get('/payment/success', function () { return 'Payment Successful!'; })->name('payment.success');
Route::get('/payment/failed', function () { return 'Payment Failed!'; })->name('payment.failed');

// Test route for alert system
Route::get('/test-alert', function () {
    return view('test-alert');
})->name('test.alert');

// Test route for domain restriction - REMOVE AFTER TESTING
Route::get('/domain-test', function () {
    return response()->json([
        'message' => 'If you see this on alldatagh.com, the restriction is NOT working',
        'host' => request()->getHost(),
        'should_be_blocked' => request()->getHost() === 'alldatagh.com'
    ]);
});

// Test route for domain restriction
Route::get('/test-domain', function () {
    return response()->json([
        'message' => 'Domain restriction test',
        'host' => request()->getHost(),
        'url' => request()->url(),
        'second_domain' => config('app.second_domain')
    ]);
});

// Public shop routes
Route::get('/shop/{slug}', [ShopController::class, 'show'])->name('shop.show');
Route::post('/shop/{slug}/add-to-cart', [ShopController::class, 'addToCart'])->name('shop.add-to-cart');
Route::get('/shop/{slug}/order-success/{order}', [ShopController::class, 'orderSuccess'])->name('shop.order-success');
Route::match(['GET', 'POST'], '/shop/{slug}/track-order', [ShopController::class, 'findOrder'])->name('shop.find-order');

// Guest payment routes
Route::post('/guest/payment/initialize', [\App\Http\Controllers\GuestPaymentController::class, 'initialize'])->name('guest.payment.initialize');
Route::get('/guest/payment/callback', [\App\Http\Controllers\GuestPaymentController::class, 'callback'])->name('guest.payment.callback');

// Shop order creation - support both GET and POST for browser compatibility
Route::match(['GET', 'POST'], '/shop/{slug}/create-order-from-payment', [ShopController::class, 'createOrderFromPayment'])->name('shop.create-order-from-payment');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';