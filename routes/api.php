<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AFAController;
use App\Http\Controllers\Api\InboundWebhookController;

use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\NumberVerificationController;
use App\Models\User;
use App\Models\Webhook;

Route::prefix('v1')->group(function () {

    // 🔑 Login & token creation (public)
    Route::post('/token/create', function (Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid login details'], 401);
        }

        $user = User::where('email', $request->email)->first();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    });

    // Get existing token (requires web auth)
    Route::middleware('auth:web')->get('/get-token', function (Request $request) {
        $user = $request->user();
        $token = $user->tokens()->where('name', 'api-token')->first();
        
        if ($token) {
            return response()->json(['token' => $token->token]);
        }
        
        return response()->json(['token' => null]);
    });

    // 🔒 All routes inside here require Sanctum token
    Route::middleware('auth:sanctum')->group(function () {
        // Check whether a beneficiary number can receive an order.
        Route::post('/verify-number', [NumberVerificationController::class, 'verify']);

        // Test endpoint for debugging
        Route::post('/test-order', function (Request $request) {
            return response()->json([
                'message' => 'Test endpoint working',
                'method' => $request->method(),
                'user' => auth()->user()->email ?? 'No user',
                'data' => $request->all()
            ]);
        });
        
        // ORDERS
        Route::get('/normal-orders', [OrderController::class, 'index']);
        Route::post('/normal-orders', [OrderController::class, 'store']);
        Route::post('/excel-orders', [OrderController::class, 'store']);

        Route::get('/afa', [AFAController::class, 'index']);
        Route::post('/afa', [AFAController::class, 'store']);
        Route::get('/afa/products', [AFAController::class, 'getProducts']);



        // TRANSACTIONS (Orders)
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show']);
        
        // ORDER TRACKING
        Route::post('/track-order', function (Request $request) {
            $request->validate([
                'payment_reference' => 'required|string',
                'beneficiary_number' => 'required|string|size:10',
                'shop_slug' => 'nullable|string'
            ]);
            
            $paymentReference = trim($request->payment_reference);
            $beneficiaryNumber = trim($request->beneficiary_number);
            $shopSlug = $request->shop_slug;
            
            // Build query for order search
            $query = Order::where('payment_reference', $paymentReference)
                ->where('is_guest_order', true);
            
            // If shop slug is provided, filter by that shop
            if ($shopSlug) {
                $shop = \App\Models\UserShop::where('slug', $shopSlug)
                    ->where('is_active', true)
                    ->first();
                    
                if (!$shop) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Shop not found'
                    ], 404);
                }
                
                $query->where(function($q) use ($shop) {
                    $q->where('agent_id', $shop->user_id)
                      ->orWhere('user_id', $shop->user_id);
                });
            }
            
            $existingOrder = $query->with(['products'])->first();
            
            if ($existingOrder) {
                // Check if the beneficiary number matches
                if ($existingOrder->beneficiary_number !== $beneficiaryNumber) {
                    return response()->json([
                        'status' => false,
                        'message' => 'This payment reference was used for an order with a different phone number.'
                    ], 400);
                }
                
                // Order found and beneficiary matches
                return response()->json([
                    'status' => true,
                    'message' => 'Order found',
                    'order' => [
                        'id' => $existingOrder->id,
                        'payment_reference' => $existingOrder->payment_reference,
                        'total' => $existingOrder->total,
                        'status' => $existingOrder->status,
                        'network' => $existingOrder->network,
                        'beneficiary_number' => $existingOrder->beneficiary_number,
                        'buyer_email' => $existingOrder->buyer_email,
                        'created_at' => $existingOrder->created_at,
                        'products' => $existingOrder->products->map(function($product) {
                            return [
                                'name' => $product->name,
                                'network' => $product->network,
                                'price' => $product->pivot->price
                            ];
                        })
                    ]
                ]);
            }
            
            // Order not found, verify payment with Paystack
            try {
                $secretKey = config('services.paystack.secret_key');
                
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $secretKey,
                ])->get("https://api.paystack.co/transaction/verify/{$paymentReference}");
                
                if ($response->successful()) {
                    $data = $response->json()['data'];
                    
                    if ($data['status'] === 'success') {
                        return response()->json([
                            'status' => false,
                            'message' => 'Payment verified but no order found. Please contact the agent to create your order.',
                            'payment_verified' => true,
                            'payment_amount' => $data['amount'] / 100
                        ]);
                    }
                }
                
                return response()->json([
                    'status' => false,
                    'message' => 'Payment reference not found or verification failed.'
                ]);
                
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Error verifying payment. Please try again.'
                ], 500);
            }
        });
        
        // Legacy endpoint
        Route::get('/transaction-status', [TransactionController::class, 'index']);

        // WEBHOOKS
        Route::post('/webhooks', function (Request $request) {
            $request->validate([
                'url' => 'required|url',
            ]);

            $webhook = $request->user()->webhooks()->create([
                'url' => $request->url,
                'secret' => bin2hex(random_bytes(16)),
            ]);

            return response()->json(['webhook' => $webhook], 201);
        });

        Route::get('/webhooks', function (Request $request) {
            return response()->json(['webhooks' => $request->user()->webhooks]);
        });

        Route::delete('/webhooks/{id}', function (Request $request, $id) {
            $request->user()->webhooks()->where('id', $id)->delete();
            return response()->json(['message' => 'Webhook deleted']);
        });

        // Logout route - revoke current token
        Route::post('/logout', function (Request $request) {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['message' => 'Logged out successfully']);
        });
        
        // Logout all devices - revoke all tokens
        Route::post('/logout-all', function (Request $request) {
            $user = $request->user();
            $deletedCount = $user->tokens()->count();
            $user->tokens()->delete();
            
            \Illuminate\Support\Facades\Log::info('Logout all tokens', [
                'user_id' => $user->id,
                'tokens_deleted' => $deletedCount
            ]);
            
            return response()->json([
                'message' => 'Logged out from all devices',
                'tokens_deleted' => $deletedCount
            ]);
        });
    });
    
});


// Inbound Webhooks (public - no auth, verified by secret)
Route::prefix('webhook')->group(function () {
    Route::post('/bundleportal', [InboundWebhookController::class, 'bundleportal']);
    Route::post('/masternetdatahub', [InboundWebhookController::class, 'masternetdatahub']);
    Route::post('/jessco', [InboundWebhookController::class, 'jessco']);
    Route::post('/skanka5', [InboundWebhookController::class, 'skanka5']);
});
