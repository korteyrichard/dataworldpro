<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class UpgradeUserToAgent extends Command
{
    protected $signature = 'user:upgrade-agent {email}';
    protected $description = 'Upgrade user to agent role';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error('User not found');
            return;
        }
        
        $user->role = 'agent';
        $user->save();
        
        $this->info("User {$user->name} upgraded to agent");
    }
}