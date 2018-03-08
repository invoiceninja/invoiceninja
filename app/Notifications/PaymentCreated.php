<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;

class PaymentCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $payment;
    protected $invoice;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($payment, $invoice)
    {
        $this->invoice = $invoice;
        $this->payment = $payment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toSlack($notifiable)
    {
        $url = 'http://www.ninja.test/subscriptions/create';

        return (new SlackMessage)
                    ->from(APP_NAME)
                    ->image('https://app.invoiceninja.com/favicon-v2.png')
                    ->content(trans('texts.received_new_payment'))
                    ->attachment(function ($attachment) {
                        $invoiceName = $this->invoice->present()->titledName;
                        $invoiceLink = $this->invoice->present()->multiAccountLink;
                        $attachment->title($invoiceName, $invoiceLink)
                                   ->fields([
                                        trans('texts.client') => $this->invoice->client->getDisplayName(),
                                        trans('texts.amount') => $this->payment->present()->amount,
                                    ]);
                    });
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
