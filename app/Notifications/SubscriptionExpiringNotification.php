<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Subscription $subscription;

    /**
     * Create a new notification instance.
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $daysUntilExpiration = now()->diffInDays($this->subscription->current_period_end);
        
        return (new MailMessage)
            ->subject('Your Subscription is Expiring Soon')
            ->greeting('Hello!')
            ->line("Your subscription will expire in {$daysUntilExpiration} day(s).")
            ->line('Expiration date: ' . $this->subscription->current_period_end->format('F j, Y'))
            ->line('To continue enjoying premium features, please renew your subscription.')
            ->action('Renew Subscription', url('/settings/subscription'))
            ->line('If you have any questions, please contact our support team.')
            ->line('Thank you for using our service!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_expiring',
            'subscription_id' => $this->subscription->id,
            'expires_at' => $this->subscription->current_period_end,
            'days_until_expiration' => now()->diffInDays($this->subscription->current_period_end),
            'message' => 'Your subscription is expiring soon. Renew to continue using premium features.',
        ];
    }
}