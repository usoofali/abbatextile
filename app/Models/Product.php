<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shop_id',
        'category_id',
        'name',
        'description',
        'photo',
        'barcode',
        'price_per_unit',
        'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'stock_quantity' => 'decimal:2',
            'price_per_unit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (empty($product->id)) {
                $product->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the shop that owns this product
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get all sales of this product
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }


    /**
     * Get the category for this product
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Pricing now simplified to a single price per the category's default unit
    public function getUnitTypeAttribute(): string
    {
        return $this->category?->default_unit_type ?? 'yard';
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Get total quantity sold
     */
    public function getTotalSoldAttribute(): float
    {
        return $this->sales()->sum('quantity');
    }

    /**
     * Get total revenue from this product
     */
    public function getTotalRevenueAttribute(): float
    {
        return $this->sales()->sum('total_price');
    }

    /**
     * Get total profit from this product
     */
    public function getTotalProfitAttribute(): float
    {
        return $this->sales()->sum('profit');
    }

    /**
     * Generate a unique barcode for the product
     */
    public static function generateBarcode(): string
    {
        do {
            $candidate = str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (self::where('barcode', $candidate)->exists());

        return $candidate;
    }

    /**
     * Boot method to auto-generate barcode
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function ($product) {
            if (empty($product->barcode)) {
                $product->barcode = self::generateBarcode();
            }
        });
    }
}
