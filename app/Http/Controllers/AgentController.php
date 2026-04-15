<?php

namespace App\Http\Controllers;

use App\Models\UserShop;
use App\Models\Product;
use App\Models\Withdrawal;
use App\Models\Setting;
use App\Services\AgentService;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{
    public function __construct(
        private AgentService $agentService,
        private CommissionService $commissionService
    ) {}

    public function dashboard()
    {
        $user = Auth::user();
        
        $stats = [
            'total_sales' => 0,
            'pending_commissions' => 0,
            'available_balance' => 0,
            'withdrawn_balance' => 0,
            'referral_earnings' => 0
        ];
        
        try {
            $stats = $this->commissionService->getAgentStats($user->id);
        } catch (\Exception $e) {
            // Use default stats if service fails
        }
        
        $shop = $user->shop;
        
        // Generate referral code if user doesn't have one
        if (!$user->referral_code) {
            $user->generateReferralCode();
            $user->refresh();
        }

        return inertia('Agent/Dashboard', [
            'stats' => $stats,
            'shop' => $shop,
            'referralCode' => $user->referral_code,
            'referralUrl' => $user->getReferralUrl()
        ]);
    }

    public function createShop(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/'
        ]);

        $user = Auth::user();

        try {
            $shop = $this->agentService->createAgentShop(
                $user, 
                $request->name,
                $request->primary_color,
                $request->background_color
            );
            return redirect()->back()->with('success', 'Shop created successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function addProduct(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'agent_price' => 'required|numeric|min:0'
        ]);

        $user = Auth::user();
        $shop = $user->shop;

        if (!$shop) {
            return redirect()->back()->with('error', 'No shop found');
        }

        try {
            $this->agentService->addProductToShop(
                $shop,
                $request->product_id,
                $request->variant_id,
                $request->agent_price
            );
            
            return redirect()->back()->with('success', 'Product added to shop successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function removeProduct(Request $request, $agentProductId)
    {
        $user = Auth::user();
        $shop = $user->shop;

        if (!$shop) {
            return redirect()->back()->with('error', 'No shop found');
        }

        try {
            $agentProduct = $shop->agentProducts()->findOrFail($agentProductId);
            $agentProduct->delete();
            
            return redirect()->back()->with('success', 'Product removed from shop successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Product not found or could not be removed');
        }
    }

    public function requestWithdrawal(Request $request)
    {
        $minWithdrawal = Setting::get('minimum_withdrawal_amount', 10);
        
        $request->validate([
            'amount' => "required|numeric|min:{$minWithdrawal}",
            'phone_number' => 'required|string|regex:/^[0-9]{10}$/',
            'network' => 'required|in:MTN,TELECEL',
            'mobile_money_name' => 'required|string|max:255'
        ]);

        $user = Auth::user();
        $stats = $this->commissionService->getAgentStats($user->id);

        // Calculate withdrawal fee (2%)
        $withdrawalFee = $request->amount * 0.02;
        $totalDeduction = $request->amount + $withdrawalFee;

        if ($totalDeduction > $stats['available_balance']) {
            return redirect()->back()->withErrors([
                'amount' => 'Insufficient balance. You need ₵' . number_format($totalDeduction, 2) . ' (including 2% fee of ₵' . number_format($withdrawalFee, 2) . ')'
            ]);
        }

        $withdrawal = Withdrawal::create([
            'agent_id' => $user->id,
            'amount' => $request->amount,
            'phone_number' => $request->phone_number,
            'network' => $request->network,
            'mobile_money_name' => $request->mobile_money_name,
            'withdrawal_fee' => $withdrawalFee,
            'net_amount' => $request->amount - $withdrawalFee,
            'status' => 'pending'
        ]);

        return redirect()->back()->with('success', 'Withdrawal request submitted successfully. You will receive ₵' . number_format($withdrawal->net_amount, 2) . ' to ' . $request->network . ' (' . $request->phone_number . ') after approval.');
    }

    public function showUpgrade(Request $request)
    {
        $referralCode = $request->query('ref');
        
        // If user is not authenticated, redirect to register with referral code
        if (!Auth::check()) {
            return redirect()->route('register', ['ref' => $referralCode]);
        }
        
        $user = Auth::user();
        $referrer = null;
        
        if ($referralCode) {
            $referrer = \App\Models\User::where('referral_code', $referralCode)->first();
        }
        
        $agentFee = Setting::get('agent_upgrade_fee', 30);
        
        return inertia('UpgradeToAgent', [
            'agentFee' => (float) $agentFee, // Ensure it's a number
            'referrer' => $referrer,
            'referralCode' => $referralCode
        ]);
    }

    public function upgradeToAgent(Request $request)
    {
        $user = Auth::user();

        try {
            // Get referrer from the referral relationship
            $referrerId = null;
            $referral = $user->referredBy;
            if ($referral) {
                $referrerId = $referral->referrer_id;
            }
            
            $this->agentService->upgradeToAgent($user, $referrerId);
            return redirect()->route('agent.dashboard')->with('success', 'Successfully upgraded to agent');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function commissions()
    {
        $user = Auth::user();
        $commissions = $user->commissions()->with(['product', 'order'])->latest()->get();
        
        return inertia('Agent/Commissions', [
            'commissions' => $commissions
        ]);
    }

    public function referrals()
    {
        $user = Auth::user();
        
        // Get users referred by this agent
        $referrals = $user->referrals()->with('referred')->get()->map(function($referral) {
            return [
                'id' => $referral->id,
                'referred_user' => [
                    'id' => $referral->referred->id,
                    'name' => $referral->referred->name,
                    'email' => $referral->referred->email,
                    'role' => $referral->referred->role,
                    'created_at' => $referral->referred->created_at
                ],
                'referred_at' => $referral->referred_at,
                'created_at' => $referral->created_at
            ];
        });
        
        // Get referral commissions earned by this agent
        $referralCommissions = \App\Models\ReferralCommission::where('referrer_id', $user->id)
            ->with('referredAgent')
            ->latest()
            ->get()
            ->map(function($commission) {
                return [
                    'id' => $commission->id,
                    'referred_agent' => [
                        'id' => $commission->referredAgent->id,
                        'name' => $commission->referredAgent->name,
                        'email' => $commission->referredAgent->email
                    ],
                    'referral_amount' => $commission->referral_amount,
                    'referral_percentage' => $commission->referral_percentage,
                    'status' => $commission->status,
                    'available_at' => $commission->available_at,
                    'created_at' => $commission->created_at
                ];
            });
        
        // Generate referral code if user doesn't have one
        if (!$user->referral_code) {
            $user->generateReferralCode();
            $user->refresh();
        }
        
        return inertia('Agent/Referrals', [
            'referrals' => $referrals,
            'referralCommissions' => $referralCommissions,
            'referralCode' => $user->referral_code,
            'referralUrl' => $user->getReferralUrl()
        ]);
    }

    public function withdrawals()
    {
        $user = Auth::user();
        $withdrawals = $user->withdrawals()->latest()->get();
        $availableBalance = 0;
        $minWithdrawal = Setting::get('minimum_withdrawal_amount', 10);
        
        try {
            $stats = $this->commissionService->getAgentStats($user->id);
            $availableBalance = $stats['available_balance'];
        } catch (\Exception $e) {
            // Use default balance if service fails
        }
        
        return inertia('Agent/Withdrawals', [
            'withdrawals' => $withdrawals,
            'availableBalance' => $availableBalance,
            'minWithdrawal' => $minWithdrawal
        ]);
    }

    public function shop()
    {
        $user = Auth::user();
        $shop = $user->shop;
        $products = $shop ? $this->agentService->getShopProducts($shop) : [];
        
        // Get available products based on user role
        $availableProducts = collect();
        
        if ($user->role === 'agent') {
            // Agents see only products with product_type = 'agent_product' and variants that are in stock
            $availableProducts = \App\Models\Product::with(['variants' => function($query) {
                    $query->where('status', 'IN STOCK');
                }])
                ->where('product_type', 'agent_product')
                ->get()
                ->filter(function($product) {
                    return $product->variants->count() > 0; // Only include products with in-stock variants
                })
                ->map(function($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'product_type' => $product->product_type,
                        'network' => $product->network,
                        'variants' => $product->variants->map(function($variant) {
                            return [
                                'id' => $variant->id,
                                'name' => $variant->getVariantNameAttribute(),
                                'price' => $variant->price
                            ];
                        })
                    ];
                });
        } elseif ($user->role === 'admin') {
            // Admin sees all products with all variants (including out of stock)
            $availableProducts = \App\Models\Product::with('variants')->get()->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'product_type' => $product->product_type,
                    'network' => $product->network,
                    'variants' => $product->variants->map(function($variant) {
                        return [
                            'id' => $variant->id,
                            'name' => $variant->getVariantNameAttribute(),
                            'price' => $variant->price
                        ];
                    })
                ];
            });
        } else {
            // Customers see customer products with only in-stock variants
            $availableProducts = \App\Models\Product::with(['variants' => function($query) {
                    $query->where('status', 'IN STOCK');
                }])
                ->where('product_type', 'customer_product')
                ->get()
                ->filter(function($product) {
                    return $product->variants->count() > 0; // Only include products with in-stock variants
                })
                ->map(function($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'product_type' => $product->product_type,
                        'network' => $product->network,
                        'variants' => $product->variants->map(function($variant) {
                            return [
                                'id' => $variant->id,
                                'name' => $variant->getVariantNameAttribute(),
                                'price' => $variant->price
                            ];
                        })
                    ];
                });
        }
        
        return inertia('Agent/Shop', [
            'shop' => $shop,
            'products' => $products,
            'availableProducts' => $availableProducts,
            'domains' => [
                'main' => config('app.main_domain', 'localhost'),
                'second' => config('app.second_domain')
            ]
        ]);
    }

    public function updateShop(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/'
        ]);

        $user = Auth::user();
        $shop = $user->shop;
        
        if (!$shop) {
            return redirect()->back()->with('error', 'Shop not found');
        }

        $shop->update([
            'name' => $request->name,
            'primary_color' => $request->primary_color,
            'background_color' => $request->background_color
        ]);

        return redirect()->back()->with('success', 'Shop updated successfully');
    }
}