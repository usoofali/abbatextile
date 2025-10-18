<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'sale_id',
        'amount',
        'mode',
        'reference',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (empty($payment->id)) {
                $payment->id = (string) Str::uuid();
            }
        });

        static::created(function (Payment $payment): void {
            // Update sale status when payment is created
            if ($payment->relationLoaded('sale') || $payment->sale) {
                $payment->sale->updateStatus();
            }
        });
    }

    /**
     * Get the sale that this payment belongs to
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the user who received this payment
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Payment mode constants
     */
    public const MODE_CASH = 'cash';
    public const MODE_TRANSFER = 'transfer';
    public const MODE_POS = 'pos';
    public const MODE_CREDIT = 'credit';

    /**
     * Get all available payment modes
     */
    public static function getPaymentModes(): array
    {
        return [
            self::MODE_CASH => 'Cash',
            self::MODE_TRANSFER => 'Bank Transfer',
            self::MODE_POS => 'POS',
            self::MODE_CREDIT => 'Credit',
        ];
    }

    /**
     * Helper method to check if payment is credit
     */
    public function isCredit(): bool
    {
        return $this->mode === self::MODE_CREDIT;
    }

    /**
     * Get payment mode display name
     */
    public function getModeDisplayNameAttribute(): string
    {
        return self::getPaymentModes()[$this->mode] ?? $this->mode;
    }
}