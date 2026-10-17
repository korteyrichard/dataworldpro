<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        Setting::updateOrCreate(['key' => 'bundleportal_mtn_2_api_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'bundleportal_mtn_3_api_enabled'], ['value' => '0']);
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'bundleportal_mtn_2_api_enabled',
            'bundleportal_mtn_3_api_enabled',
        ])->delete();
    }
};
