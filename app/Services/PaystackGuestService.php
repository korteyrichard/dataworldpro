<?php

namespace App\Services;

use App\Models\Order;
use App\Models\AgentProduct;
use App\Models\Setting;
use App\Services\OrderPusherService;
use App\Services\CodeCraftOrderPusherService;
use App\Services\JescoOrderPusherService;
use App\Services\EasyDataOrderPusherService;
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
        
        // Ensure amount is exactly what the customer should pay (no additional fees)
        $amountInKobo = (int) round(floatval($amount) * 100);
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $email,
            'amount' => $amountInKobo, // Use exact amount without any additional fees
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful()) {
                $data = $response->json()['data'];
                
                if ($data['status'] === 'success') {
                    $result = $this->createGuestOrder($data);
                    
                    \Log::info('Order creation result', [
                        'reference' => $reference,
                        'result_status' => $result['status'] ?? false,
                        'result_keys' => array_keys($result),
                        'has_order' => isset($result['order']),
                        'has_shop_slug' => isset($result['shop_slug'])
                    ]);
                    
                    // Ensure shop_slug is set in result
                    if ($result['status'] && isset($result['order']) && !isset($result['shop_slug'])) {
                        $agentProduct = AgentProduct::with('agentShop')->find($data['metadata']['agent_product_id']);
                        if ($agentProduct && $agentProduct->agentShop) {
                            $result['shop_slug'] = $agentProduct->agentShop->slug;
                            \Log::info('Added missing shop_slug to result', [
                                'shop_slug' => $result['shop_slug']
                            ]);
                        }
                    }
                    
                    return $result;
                }
            }
            
            return ['status' => false, 'message' => 'Payment verification failed'];
        } catch (\Exception $e) {
            \Log::error('Payment verification exception', [
                'reference' => $reference,
                'error' => $e->getMessage()
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
            
            if (!$agentProduct->agentShop) {
                \Log::error('Agent shop not found for product', ['agent_product_id' => $metadata['agent_product_id']]);
                return ['status' => false, 'message' => 'Shop not found for product'];
            }

            // Check if order already exists with this payment reference (system-wide check)
            $existingOrder = Order::where('payment_reference', $paymentData['reference'])->first();
            if ($existingOrder) {
                \Log::info('Order already exists for this payment reference', [
                    'existing_order_id' => $existingOrder->id,
                    'payment_reference' => $paymentData['reference'],
                    'existing_agent_id' => $existingOrder->agent_id,
                    'current_agent_id' => $agentProduct->agentShop->user_id
                ]);
                
                // If the existing order belongs to the same agent, return it
                if ($existingOrder->agent_id == $agentProduct->agentShop->user_id) {
                    return [
                        'status' => true,
                        'order' => $existingOrder,
                        'shop_slug' => $agentProduct->agentShop->slug,
                        'message' => 'Order already exists for this payment'
                    ];
                } else {
                    // Order exists but belongs to different agent
                    // Find the correct shop for better user experience
                    $correctShop = \App\Models\UserShop::where('user_id', $existingOrder->agent_id)
                        ->where('is_active', true)
                        ->first();
                    
                    if ($correctShop) {
                        return [
                            'status' => false, 
                            'message' => 'This payment reference has already been used for an order with another agent. Please visit their shop to track your order.',
                            'redirect_url' => route('shop.show', $correctShop->slug)
                        ];
                    } else {
                        return [
                            'status' => false, 
                            'message' => 'This payment reference has already been used for an order with another agent. Each payment can only be used once.'
                        ];
                    }
                }
            }

            // Convert amount from kobo to cedis
            $orderAmount = $paymentData['amount'] / 100;
            
            // Verify the payment amount is reasonable for the product price (allow for Paystack charges)
            $expectedAmount = floatval($agentProduct->agent_price);
            $priceDifference = $orderAmount - $expectedAmount;
            $maxAllowedDifference = min($expectedAmount * 0.05, 2.00); // 5% or GHS 2 max
            
            // Allow payment amount to be higher than product price (for Paystack charges)
            // but not significantly lower
            if ($priceDifference < -0.50 || $priceDifference > $maxAllowedDifference) {
                \Log::error('Price mismatch', [
                    'order_amount' => $orderAmount,
                    'expected_amount' => $expectedAmount,
                    'difference' => $priceDifference,
                    'max_allowed' => $maxAllowedDifference
                ]);
                return [
                    'status' => false, 
                    'message' => 'Payment amount (GHS ' . number_format($orderAmount, 2) . 
                        ') does not reasonably match product price (GHS ' . number_format($expectedAmount, 2) . ')'
                ];
            }

            $order = Order::create([
                'user_id' => $agentProduct->agentShop->user_id, // Set to agent's user_id for proper display
                'agent_id' => $agentProduct->agentShop->user_id,
                'total' => $orderAmount,
                'total_amount' => $orderAmount,
                'status' => 'completed',
                'payment_method' => 'paystack',
                'payment_reference' => $paymentData['reference'],
                'buyer_email' => $metadata['buyer_email'],
                'beneficiary_number' => $metadata['beneficiary_number'],
                'network' => $agentProduct->product->network,
                'is_guest_order' => true,
                'order_source' => 'shop'
            ]);

            \Log::info('Order created successfully', [
                'order_id' => $order->id, 
                'reference' => $paymentData['reference'],
                'shop_slug' => $agentProduct->agentShop->slug
            ]);

            // Attach the product to the order
            $order->products()->attach($agentProduct->product_id, [
                'quantity' => 1,
                'price' => $orderAmount, // Use the verified order amount
                'beneficiary_number' => $metadata['beneficiary_number'],
                'product_variant_id' => $agentProduct->product_variant_id,
            ]);

            // Create commission for agent
            if ($agentProduct->commission_amount > 0) {
                $commission = $agentProduct->agentShop->agent->commissions()->create([
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

            // Push order to external APIs based on network and individual service settings
            $this->pushOrderToExternalApis($order);

            return [
                'status' => true,
                'order' => $order,
                'shop_slug' => $agentProduct->agentShop->slug,
                'message' => 'Order created successfully'
            ];
        } catch (\Exception $e) {
            \Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'status' => false, 
                'message' => 'Failed to create order: ' . $e->getMessage()
            ];
        }
    }

    private function pushOrderToExternalApis(Order $order)
    {
        try {
            // Get service settings
            $jaybartEnabled = (bool) Setting::get('jaybart_order_pusher_enabled', 1);
            $codecraftEnabled = (bool) Setting::get('codecraft_order_pusher_enabled', 1);
            $jescoEnabled = (bool) Setting::get('jesco_order_pusher_enabled', 1);
            $easydataEnabled = (bool) Setting::get('easydata_order_pusher_enabled', 1);
            
            if (strtolower($order->network) === 'mtn') {
                if ($jaybartEnabled) {
                    $mtnOrderPusher = new OrderPusherService();
                    $mtnOrderPusher->pushOrderToApi($order);
                    \Log::info('Order pushed to Jaybart API', ['orderId' => $order->id, 'network' => $order->network]);
                }
                if ($jescoEnabled) {
                    $jescoOrderPusher = new JescoOrderPusherService();
                    $jescoOrderPusher->pushOrderToApi($order);
                    \Log::info('Order pushed to Jesco API', ['orderId' => $order->id, 'network' => $order->network]);
                }
                if ($easydataEnabled) {
                    $easydataOrderPusher = new EasyDataOrderPusherService();
                    $easydataOrderPusher->pushOrderToApi($order);
                    \Log::info('Order pushed to EasyData API', ['orderId' => $order->id, 'network' => $order->network]);
                }
                if (!$jaybartEnabled && !$jescoEnabled && !$easydataEnabled) {
                    \Log::info('All MTN order pushers disabled', ['orderId' => $order->id, 'network' => $order->network]);
                }
            } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime', 'at']) && $codecraftEnabled) {
                $codeCraftOrderPusher = new CodeCraftOrderPusherService();
                $codeCraftOrderPusher->pushOrderToApi($order);
                \Log::info('Order pushed to CodeCraft API', ['orderId' => $order->id, 'network' => $order->network]);
            } else {
                \Log::info('No enabled order pusher for network', ['orderId' => $order->id, 'network' => $order->network]);
            }
        } catch (\Exception $e) {
            $order->update(['order_pusher_status' => 'failed']);
            \Log::error('Failed to push order to external API', ['orderId' => $order->id, 'network' => $order->network, 'error' => $e->getMessage()]);
        }
    }
}