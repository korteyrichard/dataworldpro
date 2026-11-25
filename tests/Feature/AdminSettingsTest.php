<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\AdminController;

test('blank tutorial video urls are saved as empty strings', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    app(AdminController::class)->updateSettings(Request::create('/admin/settings', 'POST', [
        'minimum_withdrawal_amount' => 10,
        'minimum_wallet_withdrawal_amount' => 5,
        'referral_commission_amount' => 5,
        'agent_upgrade_fee' => 30,
        'track_order_video_url' => null,
        'verify_topup_video_url' => null,
        'add_product_video_url' => null,
    ]));

    expect(Setting::get('track_order_video_url'))->toBe('')
        ->and(Setting::get('verify_topup_video_url'))->toBe('')
        ->and(Setting::get('add_product_video_url'))->toBe('');
});
