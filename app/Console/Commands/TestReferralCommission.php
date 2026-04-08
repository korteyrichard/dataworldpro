<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Services\AgentService;
use Illuminate\Console\Command;

class TestReferralCommission extends Command
{
    protected $signature = 'referral:test {user_email}';
    protected $description = 'Test referral commission for a user';

    public function handle()
    {
        $email = $this->argument('user_email');
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email {$email} not found");
            return 1;
        }
        
        $this->info("Testing referral commission for: {$user->name} ({$user->email})");
        $this->line("Current role: {$user->role}");
        
        // Check if user was referred
        $referral = $user->referredBy;
        if ($referral) {
            $referrer = $referral->referrer;
            $this->info("User was referred by: {$referrer->name} ({$referrer->email})");
            
            if ($user->role === 'customer') {
                $this->line("Simulating agent upgrade...");
                
                try {
                    $agentService = app(AgentService::class);
                    $agentService->upgradeToAgent($user, $referrer->id);
                    
                    $this->info("✅ User upgraded to agent successfully!");
                    
                    // Check referral commission
                    $commission = ReferralCommission::where('referrer_id', $referrer->id)
                        ->where('referred_agent_id', $user->id)
                        ->latest()
                        ->first();
                        
                    if ($commission) {
                        $this->info("✅ Referral commission created: GHS {$commission->referral_amount}");
                        $this->line("   Status: {$commission->status}");
                        $this->line("   Percentage: {$commission->referral_percentage}%");
                    } else {
                        $this->error("❌ No referral commission found");
                    }
                    
                } catch (\Exception $e) {
                    $this->error("❌ Error: " . $e->getMessage());
                }
            } else {
                $this->line("User is already an agent");
                
                // Check existing commissions
                $commissions = ReferralCommission::where('referrer_id', $referrer->id)
                    ->where('referred_agent_id', $user->id)
                    ->get();
                    
                if ($commissions->count() > 0) {
                    $this->info("Found {$commissions->count()} referral commission(s):");
                    foreach ($commissions as $commission) {
                        $this->line("  - GHS {$commission->referral_amount} ({$commission->status})");
                    }
                } else {
                    $this->error("❌ No referral commissions found");
                }
            }
        } else {
            $this->line("User was not referred by anyone");
        }
        
        return 0;
    }
}
