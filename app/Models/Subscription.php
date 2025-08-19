<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_price_id',
        'status',
        'current_period_start',
        'current_period_end',
        'trial_start',
        'trial_end',
        'cancel_at_period_end',
        'canceled_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_start' => 'datetime',
        'trial_end' => 'datetime',
        'canceled_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription items for the subscription.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * Get the invoices for the subscription.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Determine if the subscription is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    /**
     * Determine if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->status === 'trialing' && 
               $this->trial_end && 
               $this->trial_end->isFuture();
    }

    /**
     * Determine if the subscription has expired.
     */
    public function hasExpired(): bool
    {
        return in_array($this->status, ['canceled', 'incomplete_expired', 'unpaid']) ||
               ($this->current_period_end && $this->current_period_end->isPast());
    }

    /**
     * Determine if the subscription is canceled.
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Determine if the subscription is set to cancel at period end.
     */
    public function willCancelAtPeriodEnd(): bool
    {
        return $this->cancel_at_period_end;
    }

    /**
     * Get the subscription's grace period end date.
     */
    public function gracePeriodEnd(): ?\Carbon\Carbon
    {
        if ($this->cancel_at_period_end && $this->current_period_end) {
            return $this->current_period_end;
        }

        return null;
    }

    /**
     * Determine if the subscription is within its grace period.
     */
    public function onGracePeriod(): bool
    {
        $gracePeriodEnd = $this->gracePeriodEnd();
        
        return $gracePeriodEnd && $gracePeriodEnd->isFuture();
    }
}