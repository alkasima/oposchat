<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subscription_id',
        'stripe_subscription_item_id',
        'stripe_price_id',
        'quantity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the subscription that owns the subscription item.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the user through the subscription relationship.
     */
    public function user(): BelongsTo
    {
        return $this->subscription->user();
    }

    /**
     * Determine if this is a metered subscription item.
     */
    public function isMetered(): bool
    {
        // This would typically check the Stripe price to determine if it's metered
        // For now, we'll assume non-metered unless quantity is 0
        return $this->quantity === 0;
    }

    /**
     * Get the total quantity for this subscription item.
     */
    public function getTotalQuantity(): int
    {
        return $this->quantity;
    }
}