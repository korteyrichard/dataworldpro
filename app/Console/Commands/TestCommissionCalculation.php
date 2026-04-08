<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class TestCommissionCalculation extends Command
{
    protected $signature = 'referral:test-calculation';
    protected $description = 'Test referral commission calculation logic';

    public function handle()
    {
        $this->info('Testing Referral Commission Calculation');
        $this->line('');
        
        // Get current settings - both are amounts, not percentages
        $commissionFromPercentageSetting = Setting::get('referral_commission_percentage', null);
        $commissionFromAmountSetting = Setting::get('referral_commission_amount', 10);
        $agentFee = Setting::get('agent_upgrade_fee', 30);
        
        $this->line("Current Settings:");
        $this->line("- Agent Upgrade Fee: GHS {$agentFee}");
        $this->line("- Commission (from 'percentage' setting): GHS {$commissionFromPercentageSetting}");
        $this->line("- Commission (from 'amount' setting): GHS {$commissionFromAmountSetting}");
        $this->line('');
        
        // Test the calculation logic - both settings are amounts
        if ($commissionFromPercentageSetting && $commissionFromPercentageSetting > 0) {
            $commission = $commissionFromPercentageSetting;
            $settingUsed = 'referral_commission_percentage';
        } else {
            $commission = $commissionFromAmountSetting;
            $settingUsed = 'referral_commission_amount';
        }
        
        // Calculate the actual percentage for display
        $actualPercentage = ($agentFee > 0) ? ($commission / $agentFee * 100) : 0;
        
        $this->info("Calculation Result:");
        $this->line("- Setting Used: {$settingUsed}");
        $this->line("- Commission Amount: GHS {$commission}");
        $this->line("- Actual Percentage: {$actualPercentage}%");
        
        return 0;
    }
}
