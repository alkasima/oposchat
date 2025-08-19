<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('Your Subscription has Expired')
            ->greeting('Hello!')
            ->line('Your subscription has expired.')
            ->line('Expired on: ' . $this->subscription->current_period_end->format('F j, Y'))
            ->line('Your account has been downgraded to the free tier.')
            ->line('To restore premium features, please renew your subscription.')
            ->action('Renew Subscription', url('/settings/subscription'))
            ->line('We hope to see you back soon!')
            ->line('If you have any questions, please contact our support team.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_expired',
            'subscription_id' => $this->subscription->id,
            'expired_at' => $this->subscription->current_period_end,
            'message' => 'Your subscription has expired. Renew to restore premium features.',
        ];
    }
}