<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'agent_id',
        'order_id',
        'product_id',
        'product_variant_id',
        'base_price',
        'agent_price',
        'commission_amount',
        'quantity',
        'status',
        'withdrawn_amount',
        'available_at'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'agent_price' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'withdrawn_amount' => 'decimal:2',
        'available_at' => 'datetime'
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getTotalCommissionAttribute(): float
    {
        return $this->commission_amount * $this->quantity;
    }

    public function getAvailableAmountAttribute(): float
    {
        return $this->getTotalCommissionAttribute() - $this->withdrawn_amount;
    }
}