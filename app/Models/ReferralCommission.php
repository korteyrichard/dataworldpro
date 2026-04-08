<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommission extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_agent_id',
        'commission_id',
        'referral_amount',
        'referral_percentage',
        'status',
        'withdrawn_amount',
        'available_at'
    ];

    protected $casts = [
        'referral_amount' => 'decimal:2',
        'referral_percentage' => 'decimal:2',
        'withdrawn_amount' => 'decimal:2',
        'available_at' => 'datetime'
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_agent_id');
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function getAvailableAmountAttribute(): float
    {
        return $this->referral_amount - $this->withdrawn_amount;
    }
}