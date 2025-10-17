<?php

namespace App\Models;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shop_id',
        'salesperson_id',
        'product_id',
        'quantity',
        'unit_type',
        'unit_price',
        'total_price',
        'cost_price',
        'profit',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'profit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Sale $sale): void {
            if (empty($sale->id)) {
                $sale->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the shop that this sale belongs to
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the salesperson who made this sale
     */
    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    /**
     * Get the product that was sold
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate profit for this sale
     */
    public function calculateProfit(): float
    {
        return $this->total_price - ($this->cost_price * $this->quantity);
    }

    /**
     * Scope for sales in a specific shop
     */
    public function scopeForShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope for sales by a specific salesperson
     */
    public function scopeBySalesperson($query, $salespersonId)
    {
        return $query->where('salesperson_id', $salespersonId);
    }

    /**
     * Scope for sales of a specific product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope for sales in a date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
