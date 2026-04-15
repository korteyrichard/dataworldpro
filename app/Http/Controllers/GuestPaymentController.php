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
            $agentProduct->agent_price, // Use exact agent price without any modifications
            $request->agent_product_id,
            $request->beneficiary_number
        );

        return response()->json($result);
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        
        \Log::info('Payment callback received', [
            'reference' => $reference,
            'all_params' => $request->all()
        ]);
        
        if (!$reference) {
            \Log::error('No payment reference in callback');
            return redirect()->route('home')->with('error', 'Invalid payment reference');
        }

        $result = $this->paystackService->verifyPayment($reference);
        
        \Log::info('Payment verification result', [
            'reference' => $reference,
            'result_status' => $result['status'] ?? false,
            'result_keys' => array_keys($result)
        ]);
        
        if ($result['status']) {
            $shopSlug = $result['shop_slug'] ?? null;
            $orderId = isset($result['order']) ? $result['order']->id : null;
            
            \Log::info('Payment successful, checking redirect data', [
                'shop_slug' => $shopSlug,
                'order_id' => $orderId,
                'result_keys' => array_keys($result),
                'order_exists' => isset($result['order']),
                'order_type' => isset($result['order']) ? get_class($result['order']) : 'null'
            ]);
            
            // Primary redirect attempt
            if ($shopSlug && $orderId) {
                \Log::info('Redirecting to success page (primary)', [
                    'slug' => $shopSlug,
                    'order' => $orderId
                ]);
                
                return redirect()->route('shop.order-success', [
                    'slug' => $shopSlug,
                    'order' => $orderId
                ])->with('success', 'Payment successful! Your order has been processed.');
            }
            
            // Fallback: Try to find the order and shop from the payment reference
            \Log::info('Attempting fallback order lookup', ['reference' => $reference]);
            
            $order = Order::where('payment_reference', $reference)
                ->where('is_guest_order', true)
                ->first();
                
            if ($order && $order->agent_id) {
                // Find the shop using the agent_id
                $shop = \App\Models\UserShop::where('user_id', $order->agent_id)->first();
                
                if ($shop) {
                    \Log::info('Fallback successful', [
                        'order_id' => $order->id,
                        'shop_slug' => $shop->slug
                    ]);
                    
                    return redirect()->route('shop.order-success', [
                        'slug' => $shop->slug,
                        'order' => $order->id
                    ])->with('success', 'Payment successful! Your order has been processed.');
                } else {
                    \Log::error('Fallback failed: Shop not found for agent_id', ['agent_id' => $order->agent_id]);
                }
            } else {
                \Log::error('Fallback failed: Order not found or no agent_id', [
                    'order_found' => $order ? true : false,
                    'agent_id' => $order ? $order->agent_id : null
                ]);
            }
            
            \Log::warning('Payment successful but all redirect attempts failed', [
                'shop_slug' => $shopSlug,
                'order_id' => $orderId,
                'result' => $result
            ]);
            
            return redirect()->route('home')->with('success', 'Payment successful! Your order has been processed.');
        }

        \Log::error('Payment verification failed', [
            'reference' => $reference,
            'result' => $result
        ]);
        
        return redirect()->route('home')->with('error', 'Payment verification failed');
    }
}