<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        Setting::updateOrCreate(['key' => 'jesco_mtn_express_order_pusher_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'skanka5_mtn_express_order_pusher_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'masternetgh_mtn_express_order_pusher_enabled'], ['value' => '0']);
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'jesco_mtn_express_order_pusher_enabled',
            'skanka5_mtn_express_order_pusher_enabled',
            'masternetgh_mtn_express_order_pusher_enabled',
        ])->delete();
    }
};
