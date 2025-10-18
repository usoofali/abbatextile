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
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SaleItem $saleItem): void {
            if (empty($saleItem->id)) {
                $saleItem->id = (string) Str::uuid();
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
}