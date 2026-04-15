<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'business_name',
        'password',
        'wallet_balance', // added wallet_balance to fillable
        'role', // added role to fillable
        'referral_code', // added referral_code to fillable
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance' => 'decimal:2', // cast wallet_balance as decimal
            'role' => 'string', // cast role as string
        ];
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function afaOrders()
    {
        return $this->hasMany(AFAOrders::class);
    }

    // Shop relationship (available to all roles)
    public function shop()
    {
        return $this->hasOne(UserShop::class);
    }

    // Keep agentShop for backward compatibility
    public function agentShop()
    {
        return $this->shop();
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'agent_id');
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class, 'agent_id');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredBy()
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }

    public function referralCommissions()
    {
        return $this->hasMany(ReferralCommission::class, 'referrer_id');
    }

    // Agent orders (orders placed through this agent's shop)
    public function agentOrders()
    {
        return $this->hasMany(Order::class, 'agent_id');
    }
    
    /**
     * Get the default role for the user.
     *
     * @return string
     */
    protected static function boot()
    {
        parent::boot();
    
        static::creating(function ($user) {
            $user->role = $user->role ?? 'customer';
        });
        
        static::updated(function ($user) {
            // Generate referral code when user becomes an agent
            if ($user->isDirty('role') && $user->role === 'agent' && !$user->referral_code) {
                $user->generateReferralCode();
            }
        });
    }

    /**
     * Check if the user is a customer.
     *
     * @return bool
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Check if the user is an agent.
     *
     * @return bool
     */
    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    /**
     * Check if the user is an admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Generate a unique referral code for the user.
     *
     * @return string
     */
    public function generateReferralCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (self::where('referral_code', $code)->exists());
        
        $this->update(['referral_code' => $code]);
        return $code;
    }

    /**
     * Get the referral URL for this user.
     *
     * @return string|null
     */
    public function getReferralUrl(): ?string
    {
        if (!$this->referral_code) {
            return null;
        }
        
        return url('/upgrade-to-agent?ref=' . $this->referral_code);
    }
}
