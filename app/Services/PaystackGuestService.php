<?php

namespace App\Services;

use App\Models\Order;
use App\Models\AgentProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackGuestService
{
    private $secretKey;
    private $publicKey;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
    }

    public function initializePayment($email, $amount, $agentProductId, $beneficiaryNumber)
    {
        $reference = 'guest_' . Str::random(10) . '_' . time();
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $email,
            'amount' => $amount * 100, // Convert to kobo
            'reference' => $reference,
            'callback_url' => route('guest.payment.callback'),
            'metadata' => [
                'agent_product_id' => $agentProductId,
                'beneficiary_number' => $beneficiaryNumber,
                'buyer_email' => $email
            ]
        ]);

        if ($response->successful()) {
            return [
                'status' => true,
                'data' => $response->json()['data'],
                'reference' => $reference
            ];
        }

        return [
            'status' => false,
            'message' => 'Payment initialization failed'
        ];
    }

    public function verifyPayment($reference)
    {
        try {
            \Log::info('Verifying payment', ['reference' => $reference]);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful()) {
                $data = $response->json()['data'];
                \Log::info('Payment verification response', ['status' => $data['status'], 'reference' => $reference]);
                
                if ($data['status'] === 'success') {
                    $result = $this->createGuestOrder($data);
                    
                    // Add shop slug to result for redirect
                    if ($result['status'] && isset($result['order'])) {
                        $agentProduct = AgentProduct::with('agentShop')->find($data['metadata']['agent_product_id']);
                        if ($agentProduct && $agentProduct->agentShop) {
                            $result['shop_slug'] = $agentProduct->agentShop->slug;
                        }
                    }
                    
                    return $result;
                }
            }
            
            \Log::error('Payment verification failed', ['reference' => $reference, 'response' => $response->json()]);
            return ['status' => false, 'message' => 'Payment verification failed'];
        } catch (\Exception $e) {
            \Log::error('Payment verification exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return ['status' => false, 'message' => 'Payment verification error: ' . $e->getMessage()];
        }
    }

    private function createGuestOrder($paymentData)
    {
        try {
            $metadata = $paymentData['metadata'];
            $agentProduct = AgentProduct::with(['product', 'productVariant', 'agentShop.agent'])->find($metadata['agent_product_id']);
            
            if (!$agentProduct) {
                \Log::error('Agent product not found', ['agent_product_id' => $metadata['agent_product_id']]);
                return ['status' => false, 'message' => 'Product not found'];
            }

            // Check if order already exists with this payment reference
            $existingOrder = Order::where('payment_reference', $paymentData['reference'])->first();
            if ($existingOrder) {
                \Log::info('Order already exists for payment reference', ['reference' => $paymentData['reference']]);
                return [
                    'status' => true,
                    'order' => $existingOrder,
                    'shop_slug' => $agentProduct->agentShop->slug,
                    'message' => 'Order already exists'
                ];
            }

            $order = Order::create([
                'user_id' => $agentProduct->agentShop->user_id, // Set to agent's user_id for proper display
                'agent_id' => $agentProduct->agentShop->user_id,
                'total' => $paymentData['amount'] / 100,
                'total_amount' => $paymentData['amount'] / 100,
                'status' => 'completed',
                'payment_method' => 'paystack',
                'payment_reference' => $paymentData['reference'],
                'buyer_email' => $metadata['buyer_email'],
                'beneficiary_number' => $metadata['beneficiary_number'],
                'network' => $agentProduct->product->network,
                'is_guest_order' => true
            ]);

            \Log::info('Guest order created', ['order_id' => $order->id, 'reference' => $paymentData['reference']]);

            // Attach the product to the order
            $order->products()->attach($agentProduct->product_id, [
                'quantity' => 1,
                'price' => $paymentData['amount'] / 100,
                'beneficiary_number' => $metadata['beneficiary_number'],
                'product_variant_id' => $agentProduct->product_variant_id,
            ]);

            // Create commission for agent
            if ($agentProduct->commission_amount > 0) {
                $agentProduct->agentShop->agent->commissions()->create([
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

            return [
                'status' => true,
                'order' => $order,
                'shop_slug' => $agentProduct->agentShop->slug,
                'message' => 'Order created successfully'
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to create guest order', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => false, 
                'message' => 'Failed to create order: ' . $e->getMessage()
            ];
        }
    }
}