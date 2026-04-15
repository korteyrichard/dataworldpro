<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Transaction;
use App\Models\Setting;
use Illuminate\Support\Facades\Redirect;

class BecomeAgentController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'customer') {
            return back()->withErrors(['message' => 'Unable to become an agent.']);
        }

        $reference = 'agent_' . Str::random(16);
        
        // Get agent upgrade fee from settings
        $registrationFee = Setting::get('agent_upgrade_fee', 30);
        $transactionFee = $registrationFee * 0.01;
        $totalAmount = $registrationFee + $transactionFee;
        
        // Store pending transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'order_id' => null,
            'amount' => $registrationFee,
            'status' => 'pending',
            'type' => 'agent_fee',
            'description' => 'API access fee of GHS ' . number_format($registrationFee, 2) . ' (+ GHS ' . number_format($transactionFee, 2) . ' fee)',
            'reference' => $reference,
        ]);
        
        // Initialize Paystack payment
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secret_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => (int) round($totalAmount * 100), // Convert to kobo with proper rounding
            'callback_url' => route('agent.callback'),
            'reference' => $reference,
            'metadata' => [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'type' => 'agent_registration',
                'actual_amount' => $registrationFee,
                'transaction_fee' => $transactionFee
            ]
        ]);

        if ($response->successful()) {
            $paymentUrl = $response->json('data.authorization_url');
            return \Inertia\Inertia::location($paymentUrl);
        }

        $transaction->update(['status' => 'failed']);
        return back()->withErrors(['message' => 'Payment initialization failed']);
    }

    public function handleAgentCallback(Request $request)
    {
        $reference = $request->reference;
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secret_key'),
        ])->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful() && $response->json('data.status') === 'success') {
            $paymentData = $response->json('data');
            $metadata = $paymentData['metadata'];
            
            $transaction = Transaction::find($metadata['transaction_id']);
            $user = \App\Models\User::find($metadata['user_id']);
            
            if ($transaction && $transaction->status === 'pending' && $user && $user->role === 'customer') {
                // Get referrer from the referral relationship
                $referrerId = null;
                $referral = $user->referredBy;
                if ($referral) {
                    $referrerId = $referral->referrer_id;
                }
                
                // Use AgentService to upgrade user (this handles referral commissions)
                try {
                    $agentService = app(\App\Services\AgentService::class);
                    $agentService->upgradeToAgent($user, $referrerId);
                    
                    // Generate referral code if user doesn't have one
                    if (!$user->referral_code) {
                        $user->generateReferralCode();
                    }
                } catch (\Exception $e) {
                    // Fallback: just update role if service fails
                    $user->role = 'agent';
                    $user->save();
                    
                    // Generate referral code
                    if (!$user->referral_code) {
                        $user->generateReferralCode();
                    }
                }
                
                // Update transaction status
                $transaction->update(['status' => 'completed']);
            }
        }

        return redirect()->route('dashboard')->with('success', 'You are now an agent!');
    }
}
