<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        // Insert default agent settings
        $settings = [
            ['key' => 'minimum_withdrawal_amount', 'value' => '10'],
            ['key' => 'referral_commission_amount', 'value' => '5'],
            ['key' => 'agent_upgrade_fee', 'value' => '30'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']], 
                ['value' => $setting['value']]
            );
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'minimum_withdrawal_amount',
            'referral_commission_amount', 
            'agent_upgrade_fee'
        ])->delete();
    }
};