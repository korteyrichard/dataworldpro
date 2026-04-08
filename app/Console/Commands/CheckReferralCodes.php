<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckReferralCodes extends Command
{
    protected $signature = 'referral:check';
    protected $description = 'Check referral codes for agents';

    public function handle()
    {
        $agents = User::where('role', 'agent')->get(['id', 'name', 'referral_code']);
        
        $this->info('Checking referral codes for agents:');
        
        foreach ($agents as $agent) {
            $url = $agent->getReferralUrl();
            $this->line("Agent: {$agent->name}");
            $this->line("  Code: " . ($agent->referral_code ?: 'NULL'));
            $this->line("  URL: " . ($url ?: 'NULL'));
            $this->line('');
        }
        
        return 0;
    }
}
