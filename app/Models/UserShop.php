<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserShop extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'is_active',
        'primary_color',
        'background_color'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agentProducts(): HasMany
    {
        return $this->hasMany(AgentProduct::class, 'agent_shop_id');
    }

    public function activeProducts(): HasMany
    {
        return $this->hasMany(AgentProduct::class, 'agent_shop_id')->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getPublicUrlAttribute(): string
    {
        return "/shop/{$this->slug}";
    }
}