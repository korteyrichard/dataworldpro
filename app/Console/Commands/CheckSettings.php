<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class CheckSettings extends Command
{
    protected $signature = 'settings:check';
    protected $description = 'Check current referral commission settings';

    public function handle()
    {
        $this->info('Current Referral Commission Settings:');
        $this->line('');
        
        $percentage = Setting::get('referral_commission_percentage', 'NOT SET');
        $amount = Setting::get('referral_commission_amount', 'NOT SET');
        $agentFee = Setting::get('agent_upgrade_fee', 'NOT SET');
        
        $this->line("Referral Commission Percentage: {$percentage}%");
        $this->line("Referral Commission Amount: GHS {$amount}");
        $this->line("Agent Upgrade Fee: GHS {$agentFee}");
        $this->line('');
        
        if ($percentage !== 'NOT SET' && $agentFee !== 'NOT SET') {
            $calculatedCommission = ($agentFee * $percentage / 100);
            $this->info("Calculated Commission: GHS {$calculatedCommission}");
        }
        
        return 0;
    }
}
