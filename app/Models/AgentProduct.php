<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentProduct extends Model
{
    protected $fillable = [
        'agent_shop_id',
        'product_id',
        'product_variant_id',
        'agent_price',
        'is_active'
    ];

    protected $casts = [
        'agent_price' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function agentShop(): BelongsTo
    {
        return $this->belongsTo(UserShop::class, 'agent_shop_id');
    }

    public function userShop(): BelongsTo
    {
        return $this->belongsTo(UserShop::class, 'agent_shop_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // Alias for variant relationship (for backward compatibility)
    public function variant(): BelongsTo
    {
        return $this->productVariant();
    }

    public function getCommissionAmountAttribute(): float
    {
        $basePrice = $this->productVariant ? $this->productVariant->price : 0;
        return max(0, $this->agent_price - $basePrice);
    }
}