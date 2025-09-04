<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Transaction;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Services\SmsService;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Remove products query as we're not using the old product system anymore
        
        $cartCount = 0;
        $cartItems = [];
        $walletBalance = 0;
        $orders = [];
        
        if (auth()->check()) {
            $cartCount = Cart::where('user_id', auth()->id())->count();
            $cartItems = Cart::where('user_id', auth()->id())
                ->with(['product', 'productVariant'])
                ->get()
                ->map(function($item) {
                    $size = 'Unknown';
                    if ($item->productVariant && isset($item->productVariant->variant_attributes['size'])) {
                        $size = strtoupper($item->productVariant->variant_attributes['size']);
                    }
                    
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $size,
                        'beneficiary_number' => $item->beneficiary_number,
                        'product' => [
                            'name' => $item->product ? $item->product->name : 'Data Bundle',
                            'price' => $item->price ?? ($item->productVariant ? $item->productVariant->price : 0),
                            'network' => $item->network ?? ($item->product ? $item->product->network : 'Unknown'),
                            'expiry' => $item->product ? $item->product->expiry : '30 Days'
                        ]
                    ];
                });
            $walletBalance = $user->wallet_balance;
            $orders = Order::where('user_id', $user->id)->with('products')->get();
        }
        
        // Calculate dashboard stats
        $totalSales = Transaction::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('type', 'order')
            ->sum('amount');
            
        $todaySales = Transaction::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('type', 'order')
            ->whereDate('created_at', today())
            ->sum('amount');
            
        $pendingOrdersCount = Order::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'PENDING'])
            ->count();
            
        $processingOrdersCount = Order::where('user_id', $user->id)
            ->whereIn('status', ['processing', 'PROCESSING'])
            ->count();
        
        return Inertia::render('Dashboard/dashboard', [
            'cartCount' => $cartCount,
            'cartItems' => $cartItems,
            'walletBalance' => $walletBalance,
            'orders' => $orders,
            'totalSales' => $totalSales ?? 0,
            'todaySales' => $todaySales ?? 0,
            'pendingOrders' => $pendingOrdersCount ?? 0,
            'processingOrders' => $processingOrdersCount ?? 0,
        ]);
    }



    public function viewCart()
    {
        $cartItems = Cart::where('user_id', auth()->id())
            ->with(['product', 'productVariant'])
            ->get()
            ->map(function($item) {
                $size = 'Unknown';
                if ($item->productVariant && isset($item->productVariant->variant_attributes['size'])) {
                    $size = strtoupper($item->productVariant->variant_attributes['size']);
                }
                
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $size,
                    'beneficiary_number' => $item->beneficiary_number,
                    'product' => [
                        'name' => $item->product ? $item->product->name : 'Data Bundle',
                        'price' => $item->price ?? ($item->productVariant ? $item->productVariant->price : 0),
                        'network' => $item->network ?? ($item->product ? $item->product->network : 'Unknown'),
                        'expiry' => $item->product ? $item->product->expiry : '30 Days'
                    ]
                ];
            });
        return Inertia::render('Dashboard/Cart', ['cartItems' => $cartItems]);
    }

    public function removeFromCart($id)
    {
        Cart::where('user_id', auth()->id())->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Removed from cart']);
    }

    public function transactions()
    {
        $transactions = Transaction::where('user_id', auth()->id())->latest()->get();
        return Inertia::render('Dashboard/transactions', [
            'transactions' => $transactions,
        ]);
    }

    /**
     * Add to the authenticated user's wallet balance via Paystack
     */
    public function addToWallet(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user = auth()->user();
        $reference = 'wallet_' . Str::random(16);
        
        // Calculate 1% transaction fee
        $transactionFee = $request->amount * 0.01;
        $totalAmount = $request->amount + $transactionFee;
        
        // Store pending transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'order_id' => null,
            'amount' => $request->amount,
            'status' => 'pending',
            'type' => 'topup',
            'description' => 'Wallet top-up of GHS ' . number_format($request->amount, 2) . ' (+ GHS ' . number_format($transactionFee, 2) . ' fee)',
            'reference' => $reference,
        ]);

        // Calculate 1% transaction fee
        $transactionFee = $request->amount * 0.01;
        $totalAmount = $request->amount + $transactionFee;
        
        // Initialize Paystack payment
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secret_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => $totalAmount * 100, // Convert to kobo
            'callback_url' => route('wallet.callback'),
            'reference' => $reference,
            'metadata' => [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'type' => 'wallet_topup',
                'actual_amount' => $request->amount,
                'transaction_fee' => $transactionFee
            ]
        ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'payment_url' => $response->json('data.authorization_url')
            ]);
        }

        $transaction->update(['status' => 'failed']);
        return response()->json(['success' => false, 'message' => 'Payment initialization failed']);
    }

    public function handleWalletCallback(Request $request)
    {
        $reference = $request->reference;
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secret_key'),
        ])->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful() && $response->json('data.status') === 'success') {
            $paymentData = $response->json('data');
            $metadata = $paymentData['metadata'];
            
            $transaction = Transaction::find($metadata['transaction_id']);
            $user = auth()->user();
            
            if ($transaction && $transaction->status === 'pending') {
                // Get the actual amount from metadata (excluding transaction fee)
                $actualAmount = isset($metadata['actual_amount']) ? $metadata['actual_amount'] : $transaction->amount;
                
                // Update wallet balance with the actual amount (not including fee)
                $user->wallet_balance += $actualAmount;
                $user->save();
                
                // Update transaction status
                $transaction->update(['status' => 'completed']);
                
                // Send SMS notification
                if ($user->phone) {
                    $smsService = new SmsService();
                    $message = "Your wallet has been topped up with GHS " . number_format($actualAmount, 2) . ". New balance: GHS " . number_format($user->wallet_balance, 2);
                    $smsService->sendSms($user->phone, $message);
                }
            }
        }

        return redirect()->route('dashboard')->with('success', 'Wallet topped up successfully!');
    }

    public function getBundleSizes(Request $request)
    {
        $network = $request->get('network');
        
        if (!$network) {
            return response()->json(['success' => false, 'message' => 'Network is required']);
        }
        
        $product = Product::where('network', $network)->first();
        
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found']);
        }
        
        $variants = $product->variants()
            ->where('status', 'IN STOCK')
            ->where('quantity', '>', 0)
            ->get()
            ->map(function($variant) {
                $size = $variant->variant_attributes['size'] ?? 'unknown';
                $displaySize = strtoupper(str_replace('gb', ' GB', $size));
                if ($size === '0.5gb') {
                    $displaySize = '500 MB';
                }
                return [
                    'value' => str_replace('gb', '', $size),
                    'label' => $displaySize,
                    'price' => $variant->price
                ];
            })
            ->sortBy('value')
            ->values();
            
        return response()->json(['success' => true, 'sizes' => $variants]);
    }
}
