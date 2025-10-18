<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    protected $keyType = 'string';

    public $incrementing = false;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'shop_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factory_recovery_codes',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->id)) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Role constants
     */
    public const ROLE_ADMIN = 'admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_SALESPERSON = 'salesperson';

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is manager
     */
    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /**
     * Check if user is salesperson
     */
    public function isSalesperson(): bool
    {
        return $this->role === self::ROLE_SALESPERSON;
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Get the shop that the user belongs to
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the shops managed by this user
     */
    public function managedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'manager_id');
    }

    public function managedShop(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Shop::class, 'manager_id');
    }

    /**
     * Get sales made by this user (legacy - individual product sales)
     */
    public function sales()
    {
        return $this->hasMany(Sale::class, 'salesperson_id');
    }

    /**
     * Get sales transactions made by this user
     */
    public function salesTransactions()
    {
        return $this->hasMany(Sale::class, 'salesperson_id');
    }

    /**
     * Get payments received by this user
     */
    public function receivedPayments()
    {
        return $this->hasMany(Payment::class, 'received_by');
    }
}
