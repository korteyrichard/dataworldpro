<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use App\Models\Commission;
use App\Models\ReferralCommission;
use App\Models\Order;
use App\Models\Setting;
use App\Models\UserShop;
use App\Models\VerifiedNumber;
use App\Models\UnverifiedNumber;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function withdrawals(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $query = Withdrawal::with('agent.shop')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $query->whereHas('agent', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->search . '%');
            });
        }

        $withdrawals = $query->paginate(50)->appends($request->query());

        return inertia('Admin/Withdrawals', [
            'withdrawals' => $withdrawals,
            'filters' => ['search' => $request->search]
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



    public function agents(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $search = $request->get('search');
        
        // Build query for users with shops
        $storeOwnersQuery = \App\Models\User::whereHas('shop')
            ->with(['shop', 'commissions', 'withdrawals'])
            ->withCount(['commissions', 'withdrawals']);
            
        // Build query for admins without shops
        $adminsQuery = \App\Models\User::where('role', 'admin')
            ->whereDoesntHave('shop')
            ->with(['shop', 'commissions', 'withdrawals'])
            ->withCount(['commissions', 'withdrawals']);
            
        // Apply search filter if provided
        if ($search) {
            $storeOwnersQuery->where('email', 'like', '%' . $search . '%');
            $adminsQuery->where('email', 'like', '%' . $search . '%');
        }
        
        // Get all matching users (we need to combine them before pagination)
        $storeOwners = $storeOwnersQuery->latest()->get();
        $admins = $adminsQuery->latest()->get();
        
        // Combine and transform the data
        $allUsers = $storeOwners->concat($admins)->map(function ($user) {
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
        
        // Manual pagination
        $perPage = 50;
        $currentPage = $request->get('page', 1);
        $total = $allUsers->count();
        $offset = ($currentPage - 1) * $perPage;
        $items = $allUsers->slice($offset, $perPage)->values();
        
        $paginatedUsers = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        
        $paginatedUsers->appends($request->query());

        // Calculate overall stats (use all users, not just paginated)
        $totalStores = \App\Models\UserShop::count();
        $activeStores = \App\Models\UserShop::where('is_active', true)->count();
        $totalStoreOwners = \App\Models\User::whereHas('shop')->count();
        $totalAdmins = \App\Models\User::where('role', 'admin')->count();
        
        $overallStats = [
            'total_agents' => $totalStoreOwners,
            'total_admins' => $totalAdmins,
            'agents_with_shops' => $activeStores,
            'total_agent_earnings' => $allUsers->sum('stats.total_commissions'),
            'total_pending_withdrawals' => $allUsers->sum('stats.pending_withdrawals'),
        ];

        return inertia('Admin/Agents', [
            'agents' => $paginatedUsers,
            'stats' => $overallStats,
            'filters' => [
                'search' => $search,
            ]
        ]);
    }

    public function commissions(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $query = \App\Models\Commission::with(['agent.shop', 'product', 'order'])
            ->latest();

        if ($request->filled('search')) {
            $query->whereHas('agent', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->search . '%');
            });
        }

        $commissions = $query->paginate(50)->appends($request->query());

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
            'stats' => $stats,
            'filters' => ['search' => $request->search]
        ]);
    }

    public function settings()
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $settings = [
            'minimum_withdrawal_amount' => Setting::get('minimum_withdrawal_amount', 10),
            'minimum_wallet_withdrawal_amount' => Setting::get('minimum_wallet_withdrawal_amount', 5),
            'referral_commission_amount' => Setting::get('referral_commission_amount', 5),
            'agent_upgrade_fee' => Setting::get('agent_upgrade_fee', 30),
            'track_order_video_url' => Setting::get('track_order_video_url', ''),
            'verify_topup_video_url' => Setting::get('verify_topup_video_url', ''),
            'number_restriction_enabled' => (bool) Setting::get('number_restriction_enabled', 0),
            'previous_order_restriction_enabled' => (bool) Setting::get('previous_order_restriction_enabled', 0),
        ];

        $verifiedSearch = request('verified_search', '');
        $verifiedNumbers = VerifiedNumber::when($verifiedSearch, fn($q) => $q->where('phone_number', 'like', "%{$verifiedSearch}%"))
            ->latest()->paginate(50)->withQueryString();
        $unverifiedCount = UnverifiedNumber::count();
        $unverifiedNumbers = UnverifiedNumber::orderBy('created_at', 'desc')->get();

        return inertia('Admin/Settings', [
            'settings' => $settings,
            'verifiedNumbers' => $verifiedNumbers,
            'verifiedSearch' => $verifiedSearch,
            'unverifiedCount' => $unverifiedCount,
            'unverifiedNumbers' => $unverifiedNumbers,
        ]);
    }

    public function updateSettings(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $request->validate([
            'minimum_withdrawal_amount' => 'required|numeric|min:1',
            'minimum_wallet_withdrawal_amount' => 'required|numeric|min:1',
            'referral_commission_amount' => 'required|numeric|min:0',
            'agent_upgrade_fee' => 'required|numeric|min:0',
            'track_order_video_url' => 'nullable|url',
            'verify_topup_video_url' => 'nullable|url',
        ]);

        Setting::set('minimum_withdrawal_amount', $request->minimum_withdrawal_amount);
        Setting::set('minimum_wallet_withdrawal_amount', $request->minimum_wallet_withdrawal_amount);
        Setting::set('referral_commission_amount', $request->referral_commission_amount);
        Setting::set('agent_upgrade_fee', $request->agent_upgrade_fee);
        Setting::set('track_order_video_url', $request->track_order_video_url);
        Setting::set('verify_topup_video_url', $request->verify_topup_video_url);

        return redirect()->back()->with('success', 'Store management settings updated successfully');
    }

    public function toggleNumberRestriction(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $enabled = $request->input('enabled', false);
        Setting::set('number_restriction_enabled', $enabled ? '1' : '0');

        $status = $enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "Number restriction {$status} successfully.");
    }

    public function togglePreviousOrderRestriction(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $enabled = $request->input('enabled', false);
        Setting::set('previous_order_restriction_enabled', $enabled ? '1' : '0');

        $status = $enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "Previous order restriction {$status} successfully.");
    }

    public function addVerifiedNumber(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $request->validate([
            'phone_number' => 'required|string|regex:/^[0-9]{9,10}$/|unique:verified_numbers,phone_number',
        ]);

        $phone = $request->phone_number;
        if (strlen($phone) === 9) {
            $phone = '0' . $phone;
        }

        VerifiedNumber::create(['phone_number' => $phone]);

        return redirect()->back()->with('success', 'Number added successfully.');
    }

    public function importVerifiedNumbers(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            \Log::info('Verified number import started', ['extension' => $extension, 'name' => $file->getClientOriginalName()]);

            $rows = array_map('str_getcsv', file($file->getPathname()));

            \Log::info('Rows loaded', ['count' => count($rows)]);

            $added = 0;
            $skipped = 0;

            foreach ($rows as $index => $row) {
                if ($index === 0) continue;
                $phone = trim((string) ($row[0] ?? ''));
                // Pad 9-digit numbers with leading zero
                if (preg_match('/^[0-9]{9}$/', $phone)) {
                    $phone = '0' . $phone;
                }
                \Log::info('Processing row', ['index' => $index, 'phone' => $phone]);
                if (!preg_match('/^[0-9]{10}$/', $phone)) {
                    $skipped++;
                    continue;
                }
                $result = VerifiedNumber::firstOrCreate(['phone_number' => $phone]);
                if ($result->wasRecentlyCreated) $added++; else $skipped++;
            }

            \Log::info('Import complete', ['added' => $added, 'skipped' => $skipped]);

            if ($added === 0 && $skipped > 0) {
                return redirect()->back()->with('error', "No new numbers added. All {$skipped} number(s) already exist in the verified list.");
            }

            $message = "{$added} new number(s) added.";
            if ($skipped > 0) {
                $message .= " {$skipped} already existed and were skipped.";
            }
            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            \Log::error('Verified number import failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function importVerifiedNumbersText(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $request->validate(['numbers' => 'required|string']);

        $lines = preg_split('/[\r\n,]+/', $request->numbers);
        $added = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $phone = trim($line);
            if ($phone === '') continue;
            if (preg_match('/^[0-9]{9}$/', $phone)) {
                $phone = '0' . $phone;
            }
            if (!preg_match('/^[0-9]{10}$/', $phone)) {
                $skipped++;
                continue;
            }
            $result = VerifiedNumber::firstOrCreate(['phone_number' => $phone]);
            if ($result->wasRecentlyCreated) $added++; else $skipped++;
        }

        if ($added === 0 && $skipped > 0) {
            return redirect()->back()->with('error', "No new numbers added. All {$skipped} number(s) already exist.");
        }

        $message = "{$added} new number(s) added.";
        if ($skipped > 0) $message .= " {$skipped} skipped (invalid or already exist).";
        return redirect()->back()->with('success', $message);
    }

    public function removeVerifiedNumber(VerifiedNumber $verifiedNumber)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $verifiedNumber->delete();

        return redirect()->back()->with('success', 'Number removed successfully.');
    }

    public function downloadVerifiedNumbers()
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $numbers = VerifiedNumber::orderBy('phone_number')->pluck('phone_number');

        $filename = 'verified_numbers_' . now()->format('Y_m_d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($numbers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['phone_number']);
            foreach ($numbers as $number) {
                fputcsv($handle, [$number]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadOrderBeneficiaries()
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $numbers = \DB::table('order_product')
            ->join('orders', 'orders.id', '=', 'order_product.order_id')
            ->where('orders.network', 'MTN')
            ->whereNotNull('order_product.beneficiary_number')
            ->where('order_product.beneficiary_number', '!=', '')
            ->distinct()
            ->pluck('order_product.beneficiary_number');

        $filename = 'order_beneficiaries_' . now()->format('Y_m_d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($numbers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['phone_number']);
            foreach ($numbers as $number) {
                fputcsv($handle, [$number]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadUnverifiedNumbers(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('home')->with('error', 'Access denied');
        }

        $search = $request->get('search', '');

        $query = UnverifiedNumber::query();
        if ($search) {
            $query->where('phone_number', 'like', '%' . $search . '%');
        }
        $numbers = $query->orderBy('created_at', 'desc')->get();

        // Clear the unverified numbers after fetching
        if ($search) {
            $query->delete();
        } else {
            UnverifiedNumber::truncate();
        }

        $filename = 'unverified_numbers_' . now()->format('Y_m_d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($numbers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['phone_number']);
            foreach ($numbers as $row) {
                fputcsv($handle, [$row->phone_number]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}