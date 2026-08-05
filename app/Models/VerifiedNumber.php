<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifiedNumber extends Model
{
    protected $fillable = ['phone_number'];

    public static function isVerified(string $phoneNumber): bool
    {
        return self::where('phone_number', $phoneNumber)->exists();
    }

    public static function isRestrictionEnabled(): bool
    {
        return (bool) Setting::get('number_restriction_enabled', 0);
    }

    public static function isAllowed(string $phoneNumber): bool
    {
        if (!self::isRestrictionEnabled()) {
            return true;
        }
        return self::isVerified($phoneNumber);
    }
}
