<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class TransactionsController extends Controller
{
    /**
     * Display a listing of the transactions.
     */

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Transaction::where('user_id', $user->id)
            ->select('id', 'type', 'amount', 'description', 'created_at');

        // Filter by type
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $transactions = $query->latest()->paginate(10)->appends($request->query());

        // Calculate today's stats
        $todaysTopup = Transaction::where('user_id', $user->id)
            ->whereIn('type', ['wallet_topup', 'admin_credit', 'topup', 'credit', 'wallet_credit'])
            ->whereDate('created_at', today())
            ->sum('amount');
        
        $todaysSales = Order::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->sum('total');
        
        // Calculate average daily sales
        $firstOrderDate = Order::where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->value('created_at');
        
        $averageDailySales = 0;
        if ($firstOrderDate) {
            $totalSales = Order::where('user_id', $user->id)->sum('total');
            $daysSinceFirstOrder = now()->diffInDays($firstOrderDate) + 1;
            $averageDailySales = $totalSales / $daysSinceFirstOrder;
        }

        return inertia('Dashboard/transactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['type']),
            'stats' => [
                'todaysTopup' => $todaysTopup,
                'todaysSales' => $todaysSales,
                'averageDailySales' => $averageDailySales,
            ],
        ]);
    }

}
