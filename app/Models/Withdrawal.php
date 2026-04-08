<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'agent_id',
        'amount',
        'withdrawal_fee',
        'net_amount',
        'status',
        'notes',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'withdrawal_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime'
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}