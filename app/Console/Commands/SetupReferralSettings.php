<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class SetupReferralSettings extends Command
{
    protected $signature = 'referral:setup-settings';
    protected $description = 'Setup default referral commission settings';

    public function handle()
    {
        Setting::updateOrCreate(
            ['key' => 'referral_commission_percentage'],
            ['value' => '10'] // GHS 10 commission amount (despite the confusing name)
        );
        
        Setting::updateOrCreate(
            ['key' => 'referral_commission_amount'],
            ['value' => '8'] // GHS 8 fallback amount
        );
        
        $this->info('Referral commission settings created:');
        $this->line('- Primary Commission Amount: GHS 10');
        $this->line('- Fallback Commission Amount: GHS 8');
        $this->line('');
        $this->warn('Note: Despite the setting name "referral_commission_percentage",');
        $this->warn('it actually stores a fixed amount, not a percentage.');
        
        return 0;
    }
}
