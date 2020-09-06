<?php

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

class InvoiceViewedNotification extends Notification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    protected $invitation;

    protected $invoice;

    protected $company;

    protected $settings;

    public $is_system;

    protected $contact;

    public function __construct($invitation, $company, $is_system = false, $settings = null)
    {
        $this->invoice = $invitation->invoice;
        $this->contact = $invitation->contact;
        $this->company = $company;
        $this->settings = $this->invoice->client->getMergedSettings();
        $this->is_system = $is_system;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return $this->is_system ? ['slack'] : ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

                //@TODO THESE ARE @DEPRECATED NOW we are now using app/Mail/Admin/*

        $amount = Number::formatMoney($this->invoice->amount, $this->invoice->client);
        $subject = ctrans(
            'texts.notification_invoice_viewed_subject',
            [
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->invoice->number,
                ]
        );

        $data = [
            'title' => $subject,
            'message' => ctrans(
                'texts.notification_invoice_viewed',
                [
                    'amount' => $amount,
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->invoice->number,
                ]
            ),
            'url' => config('ninja.app_url').'invoices/'.$this->invoice->hashed_id,
            'button' => ctrans('texts.view_invoice'),
            'signature' => $this->settings->email_signature,
            'logo' => $this->company->present()->logo(),
        ];

        return (new MailMessage)
                    ->subject($subject)
                    ->markdown('email.admin.generic', $data)
                    ->withSwiftMessage(function ($message) {
                        $message->getHeaders()->addTextHeader('Tag', $this->company->company_key);
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

    public function toSlack($notifiable)
    {
        $logo = $this->company->present()->logo();
        $amount = Number::formatMoney($this->invoice->amount, $this->invoice->client);

        return (new SlackMessage)
                ->success()
                ->from(ctrans('texts.notification_bot'))
                ->image($logo)
                ->content(ctrans(
                    'texts.notification_invoice_viewed',
                    [
                    'amount' => $amount,
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->invoice->number,
                ]
                ));
    }
}
