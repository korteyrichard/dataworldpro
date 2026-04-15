<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use App\Models\Commission;
use App\Models\ReferralCommission;
use App\Models\Order;
use App\Models\Setting;
use App\Models\UserShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function withdrawals()
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $withdrawals = Withdrawal::with('agent.shop')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return inertia('Admin/Withdrawals', [
            'withdrawals' => $withdrawals
        ]);
    }

    public function processWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string'
        ]);

        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        if ($withdrawal->status !== 'pending') {
            return redirect()->back()->with('error', 'Withdrawal already processed');
        }

        if ($request->action === 'approve') {
            // The total amount to deduct from available balance should be the withdrawal amount
            // The fee is deducted from the withdrawal amount, not added to it
            $totalToDeduct = $withdrawal->amount;
            
            // Mark commissions as withdrawn based on the actual withdrawal amount
            $this->markCommissionsAsWithdrawn($withdrawal->agent_id, $totalToDeduct);

            $withdrawal->update([
                'status' => 'approved',
                'notes' => $request->notes,
                'processed_at' => now()
            ]);

            return redirect()->back()->with('success', 'Withdrawal approved successfully. Agent will receive ₵' . number_format($withdrawal->net_amount, 2));
        } else {
            $withdrawal->update([
                'status' => 'rejected',
                'notes' => $request->notes,
                'processed_at' => now()
            ]);

            return redirect()->back()->with('success', 'Withdrawal rejected successfully.');
        }
    }

    private function markCommissionsAsWithdrawn(int $agentId, float $totalAmount): void
    {
        $remainingAmount = $totalAmount;
        
        \Log::info("Starting withdrawal process", [
            'agent_id' => $agentId,
            'total_amount' => $totalAmount,
            'remaining_amount' => $remainingAmount
        ]);
        
        // First, withdraw from regular commissions
        $commissions = Commission::where('agent_id', $agentId)
            ->where('status', 'available')
            ->whereRaw('(commission_amount * quantity) > withdrawn_amount')
            ->orderBy('created_at', 'asc')
            ->get();
            
        \Log::info("Available commissions", [
            'count' => $commissions->count(),
            'total_available' => $commissions->sum(function($c) { 
                return ($c->commission_amount * $c->quantity) - $c->withdrawn_amount; 
            })
        ]);
            
        foreach ($commissions as $commission) {
            if ($remainingAmount <= 0) {
                \Log::info("Stopping - no remaining amount");
                break;
            }
            
            $commissionTotal = $commission->commission_amount * $commission->quantity;
            $availableFromCommission = $commissionTotal - $commission->withdrawn_amount;
            
            \Log::info("Processing commission", [
                'commission_id' => $commission->id,
                'commission_total' => $commissionTotal,
                'already_withdrawn' => $commission->withdrawn_amount,
                'available_from_commission' => $availableFromCommission,
                'remaining_amount' => $remainingAmount
            ]);
            
            if ($availableFromCommission <= $remainingAmount) {
                // Take all available from this commission
                $commission->update([
                    'withdrawn_amount' => $commission->withdrawn_amount + $availableFromCommission,
                    'status' => $commission->withdrawn_amount + $availableFromCommission >= $commissionTotal ? 'withdrawn' : 'available'
                ]);
                $remainingAmount -= $availableFromCommission;
                \Log::info("Took all available from commission", [
                    'commission_id' => $commission->id,
                    'taken' => $availableFromCommission,
                    'remaining_amount' => $remainingAmount
                ]);
            } else {
                // Take partial amount from this commission
                $commission->update([
                    'withdrawn_amount' => $commission->withdrawn_amount + $remainingAmount
                ]);
                \Log::info("Took partial amount from commission", [
                    'commission_id' => $commission->id,
                    'taken' => $remainingAmount,
                    'new_withdrawn_amount' => $commission->withdrawn_amount + $remainingAmount
                ]);
                $remainingAmount = 0;
            }
        }
        
        // Then, withdraw from referral commissions if needed
        if ($remainingAmount > 0) {
            \Log::info("Processing referral commissions", ['remaining_amount' => $remainingAmount]);
            
            $referralCommissions = ReferralCommission::where('referrer_id', $agentId)
                ->where('status', 'available')
                ->whereRaw('referral_amount > withdrawn_amount')
                ->orderBy('created_at', 'asc')
                ->get();
                
            foreach ($referralCommissions as $referralCommission) {
                if ($remainingAmount <= 0) break;
                
                $availableFromReferral = $referralCommission->referral_amount - $referralCommission->withdrawn_amount;
                
                if ($availableFromReferral <= $remainingAmount) {
                    $referralCommission->update([
                        'withdrawn_amount' => $referralCommission->withdrawn_amount + $availableFromReferral,
                        'status' => $referralCommission->withdrawn_amount + $availableFromReferral >= $referralCommission->referral_amount ? 'withdrawn' : 'available'
                    ]);
                    $remainingAmount -= $availableFromReferral;
                    \Log::info("Took all available from referral commission", [
                        'referral_commission_id' => $referralCommission->id,
                        'taken' => $availableFromReferral,
                        'remaining_amount' => $remainingAmount
                    ]);
                } else {
                    // Partial withdrawal from referral
                    $referralCommission->update([
                        'withdrawn_amount' => $referralCommission->withdrawn_amount + $remainingAmount
                    ]);
                    \Log::info("Took partial amount from referral commission", [
                        'referral_commission_id' => $referralCommission->id,
                        'taken' => $remainingAmount
                    ]);
                    $remainingAmount = 0;
                }
            }
        }
        
        \Log::info("Withdrawal process completed", ['final_remaining_amount' => $remainingAmount]);
    }



    public function agents()
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        // Get all users who have shops, regardless of role
        $storeOwners = \App\Models\User::whereHas('shop')
            ->with(['shop', 'commissions', 'withdrawals'])
            ->withCount(['commissions', 'withdrawals'])
            ->latest()
            ->get()
            ->map(function ($user) {
                $totalCommissions = $user->commissions->sum(function ($commission) {
                    return $commission->commission_amount * $commission->quantity;
                });
                $availableCommissions = $user->commissions->where('status', 'available')->sum(function ($commission) {
                    return $commission->commission_amount * $commission->quantity;
                });
                $totalWithdrawals = $user->withdrawals->sum('amount');
                $pendingWithdrawals = $user->withdrawals->where('status', 'pending')->sum('amount');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                    'agent_shop' => $user->shop ? [
                        'name' => $user->shop->name,
                        'slug' => $user->shop->slug,
                        'is_active' => $user->shop->is_active,
                    ] : null,
                    'stats' => [
                        'total_commissions' => (float) $totalCommissions,
                        'available_commissions' => (float) $availableCommissions,
                        'total_withdrawals' => (float) $totalWithdrawals,
                        'pending_withdrawals' => (float) $pendingWithdrawals,
                        'commissions_count' => $user->commissions_count,
                        'withdrawals_count' => $user->withdrawals_count,
                    ]
                ];
            });

        // Also get admins (even if they don't have shops) for management purposes
        $admins = \App\Models\User::where('role', 'admin')
            ->whereDoesntHave('shop') // Only admins without shops (to avoid duplicates)
            ->with(['shop', 'commissions', 'withdrawals'])
            ->withCount(['commissions', 'withdrawals'])
            ->latest()
            ->get()
            ->map(function ($admin) {
                $totalCommissions = $admin->commissions->sum(function ($commission) {
                    return $commission->commission_amount * $commission->quantity;
                });
                $availableCommissions = $admin->commissions->where('status', 'available')->sum(function ($commission) {
                    return $commission->commission_amount * $commission->quantity;
                });
                $totalWithdrawals = $admin->withdrawals->sum('amount');
                $pendingWithdrawals = $admin->withdrawals->where('status', 'pending')->sum('amount');

                return [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'created_at' => $admin->created_at,
                    'agent_shop' => null,
                    'stats' => [
                        'total_commissions' => (float) $totalCommissions,
                        'available_commissions' => (float) $availableCommissions,
                        'total_withdrawals' => (float) $totalWithdrawals,
                        'pending_withdrawals' => (float) $pendingWithdrawals,
                        'commissions_count' => $admin->commissions_count,
                        'withdrawals_count' => $admin->withdrawals_count,
                    ]
                ];
            });

        // Combine store owners and admins
        $allUsers = $storeOwners->concat($admins);

        // Calculate overall stats
        $totalStores = \App\Models\UserShop::count();
        $activeStores = \App\Models\UserShop::where('is_active', true)->count();
        $totalStoreOwners = \App\Models\User::whereHas('shop')->count();
        $totalAdmins = \App\Models\User::where('role', 'admin')->count();
        
        $overallStats = [
            'total_agents' => $totalStoreOwners, // Total users with stores
            'total_admins' => $totalAdmins,
            'agents_with_shops' => $activeStores, // Active stores
            'total_agent_earnings' => $allUsers->sum('stats.total_commissions'),
            'total_pending_withdrawals' => $allUsers->sum('stats.pending_withdrawals'),
        ];

        return inertia('Admin/Agents', [
            'agents' => $allUsers,
            'stats' => $overallStats
        ]);
    }

    public function commissions()
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $commissions = \App\Models\Commission::with(['agent.shop', 'product', 'order'])
            ->latest()
            ->paginate(50);

        // Calculate commission statistics
        $stats = [
            'total_commissions' => (float) \App\Models\Commission::sum(\Illuminate\Support\Facades\DB::raw('commission_amount * quantity')),
            'pending_commissions' => (float) \App\Models\Commission::where('status', 'pending')
                ->sum(\Illuminate\Support\Facades\DB::raw('commission_amount * quantity')),
            'available_commissions' => (float) \App\Models\Commission::where('status', 'available')
                ->sum(\Illuminate\Support\Facades\DB::raw('commission_amount * quantity')),
            'withdrawn_commissions' => (float) \App\Models\Commission::where('status', 'withdrawn')
                ->sum(\Illuminate\Support\Facades\DB::raw('commission_amount * quantity')),
            'total_referral_commissions' => (float) \App\Models\ReferralCommission::sum('referral_amount'),
            'available_referral_commissions' => (float) \App\Models\ReferralCommission::where('status', 'available')
                ->sum('referral_amount'),
        ];

        return inertia('Admin/Commissions', [
            'commissions' => $commissions,
            'stats' => $stats
        ]);
    }

    public function settings()
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $settings = [
            'minimum_withdrawal_amount' => Setting::get('minimum_withdrawal_amount', 10),
            'referral_commission_amount' => Setting::get('referral_commission_amount', 5),
            'agent_upgrade_fee' => Setting::get('agent_upgrade_fee', 30),
            'track_order_video_url' => Setting::get('track_order_video_url', ''),
            'verify_topup_video_url' => Setting::get('verify_topup_video_url', ''),
        ];

        return inertia('Admin/Settings', [
            'settings' => $settings
        ]);
    }

    public function updateSettings(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $request->validate([
            'minimum_withdrawal_amount' => 'required|numeric|min:1',
            'referral_commission_amount' => 'required|numeric|min:0',
            'agent_upgrade_fee' => 'required|numeric|min:0',
            'track_order_video_url' => 'nullable|url',
            'verify_topup_video_url' => 'nullable|url',
        ]);

        Setting::set('minimum_withdrawal_amount', $request->minimum_withdrawal_amount);
        Setting::set('referral_commission_amount', $request->referral_commission_amount);
        Setting::set('agent_upgrade_fee', $request->agent_upgrade_fee);
        Setting::set('track_order_video_url', $request->track_order_video_url);
        Setting::set('verify_topup_video_url', $request->verify_topup_video_url);

        return redirect()->back()->with('success', 'Store management settings updated successfully');
    }
}