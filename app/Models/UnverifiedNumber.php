<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnverifiedNumber extends Model
{
    protected $fillable = ['phone_number', 'restriction_type', 'source'];

    public static function log(string $phoneNumber, string $restrictionType, string $source = 'unknown'): void
    {
        self::firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['restriction_type' => $restrictionType, 'source' => $source]
        );
    }
}
