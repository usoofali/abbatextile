<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Shop extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'description',
        'manager_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Shop $shop): void {
            if (empty($shop->id)) {
                $shop->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the manager of this shop
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get all products in this shop
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get all sales from this shop (legacy - individual product sales)
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get all sales transactions from this shop
     */
    public function salesTransactions(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get all salespersons in this shop
     */
    public function salespersons(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_SALESPERSON);
    }

    /**
     * Calculate total sales for this shop
     */
    public function getTotalSalesAttribute(): float
    {
        return $this->sales()->sum('total_price');
    }

    /**
     * Calculate total profit for this shop
     */
    public function getTotalProfitAttribute(): float
    {
        return $this->sales()->sum('profit');
    }

    /**
     * Get sales count for this shop
     */
    public function getSalesCountAttribute(): int
    {
        return $this->sales()->count();
    }
}
