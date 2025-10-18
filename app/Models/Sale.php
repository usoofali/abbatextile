<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shop_id',
        'salesperson_id',
        'total_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
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
     * Get all items in this sale
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Get all payments for this sale
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get total amount paid for this sale
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get remaining balance for this sale
     */
    public function getBalanceAttribute(): float
    {
        return $this->total_amount - $this->total_paid;
    }

    /**
     * Check if sale is fully paid (with floating point precision)
     */
    public function isFullyPaid(): bool
    {
        return abs($this->total_amount - $this->total_paid) < 0.01;
    }

    /**
     * Check if sale is partially paid
     */
    public function isPartiallyPaid(): bool
    {
        return $this->total_paid > 0 && !$this->isFullyPaid();
    }

    /**
     * Update sale status based on payments
     */
    public function updateStatus(): void
    {
        if ($this->isFullyPaid()) {
            $this->update(['status' => 'paid']);
        } elseif ($this->isPartiallyPaid()) {
            $this->update(['status' => 'pending']);
        }
    }

    /**
     * Cancel the sale and restore stock
     */
    public function cancel(): void
    {
        DB::transaction(function () {
            // Restore stock for all items
            foreach ($this->items as $item) {
                $item->product->increment('stock_quantity', $item->quantity);
            }

            $this->update(['status' => 'cancelled']);
        });
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeForShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeBySalesperson($query, $salespersonId)
    {
        return $query->where('salesperson_id', $salespersonId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}