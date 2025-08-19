<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subscription_id',
        'stripe_invoice_id',
        'amount_paid',
        'currency',
        'status',
        'invoice_pdf',
        'hosted_invoice_url',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount_paid' => 'integer',
    ];

    /**
     * Get the user that owns the invoice.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription associated with the invoice.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Determine if the invoice has been paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Determine if the invoice is open (unpaid).
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Determine if the invoice is void.
     */
    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /**
     * Determine if the invoice is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Get the formatted amount paid.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount_paid / 100, 2);
    }

    /**
     * Get the amount in dollars (converted from cents).
     */
    public function getAmountInDollarsAttribute(): float
    {
        return $this->amount_paid / 100;
    }

    /**
     * Get the currency symbol.
     */
    public function getCurrencySymbolAttribute(): string
    {
        return match (strtoupper($this->currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => strtoupper($this->currency),
        };
    }
}