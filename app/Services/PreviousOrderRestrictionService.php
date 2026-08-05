<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;

class PreviousOrderRestrictionService
{
    public static function isRestrictionEnabled(): bool
    {
        return (bool) Setting::get('previous_order_restriction_enabled', 0);
    }

    public static function hasExistingOrder(string $beneficiaryNumber): bool
    {
        return Order::where('beneficiary_number', $beneficiaryNumber)->exists();
    }

    public static function isAllowed(string $beneficiaryNumber, string $network = ''): bool
    {
        if (strtolower($network) !== 'mtn') {
            return true;
        }

        if (!self::isRestrictionEnabled()) {
            return true;
        }

        return self::hasExistingOrder($beneficiaryNumber);
    }
}
