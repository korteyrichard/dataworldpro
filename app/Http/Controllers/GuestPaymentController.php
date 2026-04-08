<?php

namespace App\Http\Controllers;

use App\Services\PaystackGuestService;
use Illuminate\Http\Request;

class GuestPaymentController extends Controller
{
    public function __construct(
        private PaystackGuestService $paystackService
    ) {}

    public function initialize(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'agent_product_id' => 'required|exists:agent_products,id',
            'beneficiary_number' => 'required|string'
        ]);

        $agentProduct = \App\Models\AgentProduct::find($request->agent_product_id);
        
        $result = $this->paystackService->initializePayment(
            $request->email,
            $agentProduct->agent_price,
            $request->agent_product_id,
            $request->beneficiary_number
        );

        return response()->json($result);
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return redirect()->route('home')->with('error', 'Invalid payment reference');
        }

        $result = $this->paystackService->verifyPayment($reference);
        
        if ($result['status']) {
            $shopSlug = $result['shop_slug'] ?? null;
            $orderId = $result['order']->id ?? null;
            
            if ($shopSlug && $orderId) {
                return redirect()->route('shop.order-success', [
                    'slug' => $shopSlug,
                    'order' => $orderId
                ]);
            }
            
            return redirect()->route('home')->with('success', 'Payment successful! Your order has been processed.');
        }

        return redirect()->route('home')->with('error', 'Payment verification failed');
    }
}