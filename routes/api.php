<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AFAController;

use App\Http\Controllers\Api\TransactionController;
use App\Models\User;

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

    // 🔒 All routes inside here require Sanctum token
    Route::middleware('auth:sanctum')->group(function () {
        // ORDERS
        Route::get('/normal-orders', [OrderController::class, 'index']);
        Route::post('/normal-orders', [OrderController::class, 'store']);
        Route::post('/excel-orders', [OrderController::class, 'store']);

        Route::get('/afa', [AFAController::class, 'index']);
        Route::post('/afa', [AFAController::class, 'store']);
        Route::get('/afa/products', [AFAController::class, 'getProducts']);



        // TRANSACTIONS
        Route::get('/transaction-status', [TransactionController::class, 'index']);

        // Example: logout route
        Route::post('/logout', function (Request $request) {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['message' => 'Logged out']);
        });
    });
    
});
