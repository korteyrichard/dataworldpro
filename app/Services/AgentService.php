<?php

namespace App\Services;

use App\Models\User;
use App\Models\AgentShop;
use App\Models\AgentProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AgentService
{
    public function createAgentShop(User $user, string $shopName, string $primaryColor = '#3B82F6', string $backgroundColor = '#F1F5F9'): AgentShop
    {
        if ($user->agentShop) {
            throw new \Exception('User already has an agent shop');
        }

        $slug = $this->generateUniqueSlug($shopName);

        return AgentShop::create([
            'user_id' => $user->id,
            'name' => $shopName,
            'slug' => $slug,
            'is_active' => true,
            'primary_color' => $primaryColor,
            'background_color' => $backgroundColor
        ]);
    }

    public function addProductToShop(AgentShop $shop, int $productId, ?int $variantId, float $agentPrice): AgentProduct
    {
        $product = Product::findOrFail($productId);
        $variant = $variantId ? ProductVariant::findOrFail($variantId) : null;

        // Validate agent price is >= base price
        $basePrice = $variant ? $variant->price : $product->firstVariant()?->price ?? 0;
        
        if ($agentPrice < $basePrice) {
            throw new \Exception('Agent price must be greater than or equal to base price');
        }

        return AgentProduct::updateOrCreate(
            [
                'agent_shop_id' => $shop->id,
                'product_id' => $productId,
                'product_variant_id' => $variantId
            ],
            [
                'agent_price' => $agentPrice,
                'is_active' => true
            ]
        );
    }

    public function upgradeToAgent(User $user, ?int $referrerId = null): void
    {
        if ($user->role === 'agent') {
            throw new \Exception('User is already an agent');
        }

        $user->update(['role' => 'agent']);

        // Check if user was already referred during registration
        $existingReferral = $user->referredBy;
        
        if ($existingReferral) {
            // User was referred during registration, process referral commission
            $this->processReferralCommission($existingReferral->referrer, $user);
        } elseif ($referrerId) {
            // Create new referral record if not already referred
            $referrer = User::where('id', $referrerId)->where('role', 'agent')->first();
            if ($referrer) {
                $referral = \App\Models\Referral::create([
                    'referrer_id' => $referrerId,
                    'referred_id' => $user->id,
                    'referred_at' => now()
                ]);
                
                // Process referral commission
                $this->processReferralCommission($referrer, $user);
            }
        }
    }
    
    private function processReferralCommission(User $referrer, User $referredUser): void
    {
        // Get referral commission amount from admin settings
        $commission = \App\Models\Setting::get('referral_commission_amount', 8);
        $agentFee = \App\Models\Setting::get('agent_upgrade_fee', 30);
        
        // Calculate the actual percentage for display purposes
        $actualPercentage = ($agentFee > 0) ? ($commission / $agentFee * 100) : 0;
        
        // Create referral commission record
        \App\Models\ReferralCommission::create([
            'referrer_id' => $referrer->id,
            'referred_agent_id' => $referredUser->id,
            'referral_amount' => $commission,
            'referral_percentage' => $actualPercentage,
            'status' => 'available', // Make it immediately available
            'available_at' => now()
        ]);
        
        // Add commission to referrer's available balance
        $referrer->increment('wallet_balance', $commission);
        
        // Log the commission
        \Log::info('Referral commission processed', [
            'referrer_id' => $referrer->id,
            'referred_agent_id' => $referredUser->id,
            'agent_fee' => $agentFee,
            'commission_amount' => $commission,
            'actual_percentage' => $actualPercentage,
            'setting_used' => 'referral_commission_amount'
        ]);
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (AgentShop::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function getShopProducts(AgentShop $shop)
    {
        return $shop->activeProducts()
            ->with(['product', 'productVariant'])
            ->get()
            ->map(function ($agentProduct) {
                return [
                    'id' => $agentProduct->id,
                    'product' => [
                        'name' => $agentProduct->product->name,
                        'network' => $agentProduct->product->network,
                        'description' => $agentProduct->product->description,
                    ],
                    'variant' => [
                        'name' => $agentProduct->productVariant->variant_name ?? 'Standard',
                        'price' => $agentProduct->productVariant->price ?? 0,
                        'variant_attributes' => $agentProduct->productVariant->variant_attributes ?? [],
                    ],
                    'agent_price' => $agentProduct->agent_price,
                    'commission_amount' => $agentProduct->commission_amount
                ];
            });
    }
}