<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateReferralCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referral:generate-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate referral codes for existing agents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $agents = User::where('role', 'agent')
            ->whereNull('referral_code')
            ->get();

        if ($agents->isEmpty()) {
            $this->info('No agents without referral codes found.');
            return;
        }

        $this->info("Found {$agents->count()} agents without referral codes.");
        
        $bar = $this->output->createProgressBar($agents->count());
        $bar->start();

        foreach ($agents as $agent) {
            $agent->generateReferralCode();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Referral codes generated successfully!');
    }
}
