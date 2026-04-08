<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Order;
use App\Models\AgentProduct;
use App\Models\ReferralCommission;
use App\Models\Referral;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function calculateAndCreateCommissions(Order $order): void
    {
        if (!$order->agent_id) {
            return; // No agent, no commission
        }

        foreach ($order->products as $product) {
            $agentProduct = AgentProduct::where('agent_shop_id', $order->agent->agentShop->id)
                ->where('product_id', $product->id)
                ->where('product_variant_id', $product->pivot->product_variant_id)
                ->first();

            if (!$agentProduct) {
                continue; // Agent doesn't sell this product
            }

            $basePrice = $product->pivot->price;
            $agentPrice = $agentProduct->agent_price;
            $commissionAmount = max(0, $agentPrice - $basePrice);

            if ($commissionAmount > 0) {
                $commission = Commission::create([
                    'agent_id' => $order->agent_id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $product->pivot->product_variant_id,
                    'base_price' => $basePrice,
                    'agent_price' => $agentPrice,
                    'commission_amount' => $commissionAmount,
                    'quantity' => $product->pivot->quantity,
                    'status' => 'pending'
                ]);

                // Create referral commission if agent was referred
                $this->createReferralCommission($commission);
            }
        }
    }

    public function makeCommissionsAvailable(Order $order): void
    {
        if ($order->status !== 'completed') {
            return;
        }

        $commissions = Commission::where('order_id', $order->id)
            ->where('status', 'pending')
            ->get();

        foreach ($commissions as $commission) {
            $commission->update([
                'status' => 'available',
                'available_at' => Carbon::now()
            ]);

            // Also make referral commissions available
            ReferralCommission::where('commission_id', $commission->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'available',
                    'available_at' => Carbon::now()
                ]);
        }
    }

    public function reverseCommissions(Order $order): void
    {
        // For refunds/cancellations
        Commission::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'available'])
            ->delete();

        ReferralCommission::whereHas('commission', function ($query) use ($order) {
            $query->where('order_id', $order->id);
        })->delete();
    }

    private function createReferralCommission(Commission $commission): void
    {
        $referral = Referral::where('referred_id', $commission->agent_id)->first();
        
        if (!$referral) {
            return; // Agent wasn't referred
        }

        $referralAmount = Setting::get('referral_commission_amount', 5);
        $referralCommissionAmount = $referralAmount; // Use fixed amount instead of percentage

        ReferralCommission::create([
            'referrer_id' => $referral->referrer_id,
            'referred_agent_id' => $commission->agent_id,
            'commission_id' => $commission->id,
            'referral_amount' => $referralCommissionAmount,
            'referral_percentage' => 0, // Fixed amount, not percentage
            'status' => 'pending'
        ]);
    }

    public function getAgentStats(int $agentId): array
    {
        // Calculate total sales (all commissions)
        $totalSales = Commission::where('agent_id', $agentId)
            ->sum(DB::raw('commission_amount * quantity'));

        // Calculate pending commissions
        $pendingCommissions = Commission::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->sum(DB::raw('commission_amount * quantity'));

        // Calculate available balance from commissions (total - withdrawn)
        $availableCommissions = Commission::where('agent_id', $agentId)
            ->where('status', 'available')
            ->sum(DB::raw('(commission_amount * quantity) - withdrawn_amount'));

        // Calculate available balance from referral commissions (total - withdrawn)
        $availableReferrals = ReferralCommission::where('referrer_id', $agentId)
            ->where('status', 'available')
            ->sum(DB::raw('referral_amount - withdrawn_amount'));

        // Calculate withdrawn balance from commissions
        $withdrawnCommissions = Commission::where('agent_id', $agentId)
            ->sum('withdrawn_amount');

        // Calculate withdrawn balance from referral commissions
        $withdrawnReferrals = ReferralCommission::where('referrer_id', $agentId)
            ->sum('withdrawn_amount');

        // Calculate total referral earnings
        $referralEarnings = ReferralCommission::where('referrer_id', $agentId)
            ->sum('referral_amount');

        return [
            'total_sales' => (float) ($totalSales ?? 0),
            'pending_commissions' => (float) ($pendingCommissions ?? 0),
            'available_balance' => (float) (($availableCommissions ?? 0) + ($availableReferrals ?? 0)),
            'withdrawn_balance' => (float) (($withdrawnCommissions ?? 0) + ($withdrawnReferrals ?? 0)),
            'referral_earnings' => (float) ($referralEarnings ?? 0)
        ];
    }
}