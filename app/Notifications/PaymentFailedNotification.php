<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Invoice $invoice;

    /**
     * Create a new notification instance.
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
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
        $amount = number_format($this->invoice->amount_paid / 100, 2);
        
        return (new MailMessage)
            ->subject('Payment Failed - Action Required')
            ->greeting('Hello!')
            ->line('We were unable to process your recent payment.')
            ->line("Amount: {$amount} {$this->invoice->currency}")
            ->line('Please update your payment method to continue using our premium features.')
            ->action('Update Payment Method', $this->getCustomerPortalUrl())
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
            'type' => 'payment_failed',
            'invoice_id' => $this->invoice->id,
            'stripe_invoice_id' => $this->invoice->stripe_invoice_id,
            'amount' => $this->invoice->amount_paid,
            'currency' => $this->invoice->currency,
            'message' => 'Your payment failed. Please update your payment method.',
        ];
    }

    private function getCustomerPortalUrl(): string
    {
        // This would typically generate a customer portal URL
        // For now, return a generic billing page URL
        return url('/settings/subscription');
    }
}