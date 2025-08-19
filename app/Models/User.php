<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'stripe_customer_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's chats
     */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class)->orderBy('last_message_at', 'desc');
    }

    /**
     * Get the user's recent chats
     */
    public function recentChats(int $limit = 10): HasMany
    {
        return $this->chats()->limit($limit);
    }

    /**
     * Get the user's messages
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the user's streaming sessions
     */
    public function streamingSessions(): HasMany
    {
        return $this->hasMany(StreamingSession::class);
    }

    /**
     * Get the user's active streaming sessions
     */
    public function activeStreamingSessions(): HasMany
    {
        return $this->streamingSessions()->where('status', 'active');
    }

    /**
     * Get the user's subscriptions
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the user's active subscription
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->first();
    }

    /**
     * Get the user's subscription (alias for activeSubscription for backward compatibility)
     */
    public function getSubscriptionAttribute(): ?Subscription
    {
        return $this->activeSubscription();
    }

    /**
     * Get the user's invoices
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Determine if the user has an active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    /**
     * Determine if the user is on a trial
     */
    public function onTrial(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription && $subscription->onTrial();
    }

    /**
     * Determine if the user's subscription is canceled but still active
     */
    public function onGracePeriod(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription && $subscription->onGracePeriod();
    }

    /**
     * Get the name of the Stripe ID column.
     */
    public function getStripeIdColumn(): string
    {
        return 'stripe_customer_id';
    }

    /**
     * Get or create a Stripe customer for this user
     */
    public function createOrGetStripeCustomer(array $options = []): \Stripe\Customer
    {
        if ($this->hasStripeId()) {
            return $this->asStripeCustomer();
        }

        $customer = $this->createAsStripeCustomer(array_merge([
            'name' => $this->name,
            'email' => $this->email,
        ], $options));

        return $customer;
    }

    /**
     * Get the user's Stripe customer ID
     */
    public function getStripeCustomerId(): ?string
    {
        return $this->stripe_customer_id;
    }

    /**
     * Compatibility shim: map Cashier's expected `stripe_id` attribute
     * to our `stripe_customer_id` column so package code that reads/writes
     * $user->stripe_id will operate on the correct DB column.
     */
    public function getStripeIdAttribute(): ?string
    {
        return $this->attributes['stripe_customer_id'] ?? null;
    }

    public function setStripeIdAttribute($value): void
    {
        $this->attributes['stripe_customer_id'] = $value;
    }

    /**
     * Determine if the user has premium access
     */
    public function hasPremiumAccess(): bool
    {
        return $this->hasActiveSubscription() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Get the user's usage records
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    /**
     * Get the user's subscription status
     */
    public function subscriptionStatus(): string
    {
        $subscription = $this->activeSubscription();
        
        if (!$subscription) {
            return 'none';
        }

        return $subscription->status;
    }
}
