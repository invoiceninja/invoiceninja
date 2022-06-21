<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Notifications\Admin;

use App\Utils\Number;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NewPaymentNotification extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    protected $payment;

    protected $company;

    protected $settings;

    protected $is_system;

    public $method;

    public function __construct($payment, $company, $is_system = false, $settings = null)
    {
        $this->payment = $payment;
        $this->company = $company;
        $this->settings = $payment->client->getMergedSettings();
        $this->is_system = $is_system;
        $this->method = null;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return $this->method ?: [];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
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

    public function toSlack($notifiable)
    {
        $logo = $this->company->present()->logo();
        $amount = Number::formatMoney($this->payment->amount, $this->payment->client);
        $invoice_texts = ctrans('texts.invoice_number_short');

        foreach ($this->payment->invoices as $invoice) {
            $invoice_texts .= $invoice->number.',';
        }

        $invoice_texts = substr($invoice_texts, 0, -1);

        return (new SlackMessage)
                ->success()
                //->to("#devv2")
                ->from('System')
                ->image($logo)
                ->content(ctrans(
                    'texts.notification_payment_paid',
                    ['amount' => $amount,
                        'client' => $this->payment->client->present()->name(),
                        'invoice' => $invoice_texts, ]
                ));
    }
}
