<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;

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
        'subscription_type',
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
            'verification_email_sent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's subscription type, defaulting to 'free' when not set.
     */
    public function getSubscriptionTypeAttribute($value): string
    {
        return $value ?? 'free';
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
        // When a user has multiple active/trialing subscriptions (e.g. upgraded from Premium to Plus),
        // always treat the most recent one as the current plan.
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->orderByDesc('current_period_start')
            ->orderByDesc('created_at')
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

        // Save the Stripe customer ID to the user record
        $this->update(['stripe_customer_id' => $customer->id]);

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
     * Check if user has a Stripe customer ID
     */
    public function hasStripeId(): bool
    {
        return !empty($this->stripe_customer_id);
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

    /**
     * Get the user's current subscription plan name
     */
    public function getCurrentPlanName(): string
    {
        $subscription = $this->activeSubscription();
        
        if (!$subscription) {
            return 'Free';
        }

        // Get plan name from config based on stripe_price_id
        $plans = config('subscription.plans');
        foreach ($plans as $key => $plan) {
            if ($plan['stripe_price_id'] === $subscription->stripe_price_id) {
                return $plan['name'];
            }
        }

        return 'Unknown';
    }

    /**
     * Get the user's current subscription plan key
     */
    public function getCurrentPlanKey(): string
    {
        $subscription = $this->activeSubscription();
        
        if (!$subscription) {
            return 'free';
        }

        // Get plan key from config based on stripe_price_id
        $plans = config('subscription.plans');
        foreach ($plans as $key => $plan) {
            if ($plan['stripe_price_id'] === $subscription->stripe_price_id) {
                return $key;
            }
        }

        return 'free';
    }

    /**
     * Get the limit for a specific feature based on user's current plan
     */
    public function getFeatureLimit(string $feature): ?int
    {
        $planKey = $this->getCurrentPlanKey();
        $features = config('subscription.features');
        
        if (!isset($features[$feature])) {
            return null;
        }

        $featureConfig = $features[$feature];
        $limitKey = $planKey . '_limit';
        
        return $featureConfig[$limitKey] ?? null;
    }

    /**
     * Check if user has access to a specific feature
     */
    public function hasFeatureAccess(string $feature): bool
    {
        $limit = $this->getFeatureLimit($feature);
        
        // If limit is null, feature is unlimited
        if ($limit === null) {
            return true;
        }
        
        // If limit is 0, feature is not allowed
        if ($limit === 0) {
            return false;
        }
        
        // For now, we'll use the existing usage service logic
        // This can be enhanced later with more sophisticated usage tracking
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Email verification helpers
    |--------------------------------------------------------------------------
    */

    public function needsEmailVerification(): bool
    {
        return is_null($this->email_verified_at);
    }

    public function generateEmailVerificationToken(): string
    {
        $token = hash('sha256', $this->id.'|'.Str::uuid().'|'.now()->timestamp);
        $this->email_verification_token = $token;
        $this->verification_email_sent_at = now();
        $this->verification_attempts = (int)($this->verification_attempts ?? 0);
        $this->save();
        return $token;
    }

    public function getEmailVerificationUrl(): string
    {
        $token = $this->email_verification_token ?: $this->generateEmailVerificationToken();
        return URL::temporarySignedRoute(
            'email.verify',
            now()->addHours(24),
            ['token' => $token]
        );
    }

    public function verifyEmail(string $token): bool
    {
        if (!$this->email_verification_token || !hash_equals($this->email_verification_token, $token)) {
            return false;
        }

        // Expire after 24 hours
        if ($this->verification_email_sent_at instanceof Carbon &&
            $this->verification_email_sent_at->addHours(24)->isPast()) {
            return false;
        }

        $this->email_verified_at = now();
        $this->email_verification_token = null;
        $this->verification_attempts = 0;
        $this->save();
        return true;
    }

    public function canResendVerificationEmail(): bool
    {
        // Allow if never sent, or last sent > 12 minutes ago
        if (!$this->verification_email_sent_at) {
            return true;
        }
        return $this->verification_email_sent_at->diffInMinutes(now()) >= 12;
    }

    public function resendVerificationEmail(): void
    {
        $this->generateEmailVerificationToken();
        $verificationUrl = $this->getEmailVerificationUrl();
        // Email sending is handled in the controller via the external email API
    }

    /**
     * Send the password reset notification using the external email API
     * instead of Laravel's default mailer (SES).
     */
    public function sendPasswordResetNotification($token): void
    {
        // Build the password reset URL used by the frontend
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ], false));

        $html = view('emails.password-reset', [
            'user' => $this,
            'resetUrl' => $resetUrl,
        ])->render();

        $payload = [
            'from' => [
                'email' => config('services.email_api.from_email'),
                'name' => config('services.email_api.from_name'),
            ],
            'to' => [[
                'email' => $this->email,
                'name' => $this->name,
            ]],
            'subject' => 'Restablece tu contraseña en OposChat',
            'html_part' => $html,
            'text_part_auto' => true,
        ];

        Http::withHeaders([
            'content-type' => 'application/json',
            'x-auth-token' => config('services.email_api.token'),
        ])->post(config('services.email_api.url'), $payload);
    }
}
