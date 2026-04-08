<?php

namespace App\Services;

use App\Models\Order;
use App\Models\AgentProduct;
use Illuminate\Support\Facades\Session;

class CheckoutIntegrationService
{
    /**
     * Modify cart items to use agent pricing if shopping through agent shop
     */
    public function applyAgentPricing(array $cartItems): array
    {
        $agentId = Session::get('agent_id');
        
        if (!$agentId) {
            return $cartItems; // No agent, use regular pricing
        }

        $agentShop = \App\Models\AgentShop::where('user_id', $agentId)->first();
        
        if (!$agentShop) {
            return $cartItems; // Agent has no shop
        }

        foreach ($cartItems as &$item) {
            $agentProduct = AgentProduct::where('agent_shop_id', $agentShop->id)
                ->where('product_id', $item['product_id'])
                ->where('product_variant_id', $item['product_variant_id'])
                ->where('is_active', true)
                ->first();

            if ($agentProduct) {
                $item['price'] = $agentProduct->agent_price;
                $item['is_agent_product'] = true;
                $item['agent_id'] = $agentId;
            }
        }

        return $cartItems;
    }

    /**
     * Set agent_id on order during checkout
     */
    public function setOrderAgent(Order $order): void
    {
        $agentId = Session::get('agent_id');
        
        if ($agentId && config('agent.agent_system_enabled')) {
            $order->update(['agent_id' => $agentId]);
        }
    }

    /**
     * Clear agent session after successful order
     */
    public function clearAgentSession(): void
    {
        Session::forget('agent_id');
    }

    /**
     * Check if current session has an agent
     */
    public function hasAgent(): bool
    {
        return Session::has('agent_id');
    }

    /**
     * Get current agent info
     */
    public function getCurrentAgent(): ?array
    {
        $agentId = Session::get('agent_id');
        
        if (!$agentId) {
            return null;
        }

        $agent = \App\Models\User::with('agentShop')->find($agentId);
        
        return $agent ? [
            'id' => $agent->id,
            'name' => $agent->name,
            'shop_name' => $agent->agentShop?->name,
            'shop_slug' => $agent->agentShop?->slug
        ] : null;
    }
}