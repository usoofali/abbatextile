<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SaleItem extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'price',
        'purchase_price',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SaleItem $saleItem): void {
            if (empty($saleItem->id)) {
                $saleItem->id = (string) Str::uuid();
            }

            // Auto-populate purchase_price from product if not set
            if (is_null($saleItem->purchase_price) && $saleItem->product) {
                $saleItem->purchase_price = (float) ($saleItem->product->purchase_price ?? 0);
            }

            // Auto-calculate subtotal
            $saleItem->subtotal = $saleItem->calculateSubtotal();
        });

        static::updating(function (SaleItem $saleItem): void {
            $saleItem->subtotal = $saleItem->calculateSubtotal();
        });
    }

    /**
     * Get the sale that this item belongs to
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the product that was sold
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate subtotal for this item
     */
    public function calculateSubtotal(): float
    {
        return $this->quantity * $this->price;
    }

    /**
     * Calculate profit for this sale item
     */
    public function calculateProfit(): float
    {
        $purchasePrice = $this->purchase_price ?? 0;
        return ($this->price - $purchasePrice) * $this->quantity;
    }

    /**
     * Get profit margin percentage for this sale item
     */
    public function getProfitMargin(): float
    {
        if ($this->price <= 0) {
            return 0;
        }

        $purchasePrice = $this->purchase_price ?? 0;
        if ($purchasePrice <= 0) {
            return 100;
        }

        return (($this->price - $purchasePrice) / $this->price) * 100;
    }
}