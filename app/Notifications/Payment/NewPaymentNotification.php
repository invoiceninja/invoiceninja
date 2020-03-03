<?php

namespace App\Notifications\Payment;

use App\Mail\Signup\NewSignup;
use App\Utils\Number;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class NewPaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    
    protected $payment;

    protected $company;

    protected $settings; 

    public function __construct($payment, $company, $settings = null)
    {
        $this->payment = $payment;
        $this->company = $company;
        $this->settings = $payment->client->getMergedSettings();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        //return ['mail'];
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $amount = Number::formatMoney($this->payment->amount, $this->payment->client);
        
        $invoice_texts = ctrans('texts.invoice_number_short');

        foreach($this->payment->invoices as $invoice)
        {
            $invoice_texts .= $invoice->number . ',';
        }
        
        $invoice_texts = rtrim($invoice_texts);

        $data = [
            'title' => ctrans('texts.notification_payment_paid_subject', ['client' => $this->payment->client->present()->name()]),
            'message' => ctrans('texts.notification_paid_paid', ['amount' => $amount, 'client' => $this->payment->client->present()->name(), 'invoice' => $invoice_texts]),
            'url' => config('ninja.site_url') . '/payments/' . $this->payment->hashed_id,
            'button' => ctrans('texts.view_payment'),
            'signature' => $this->settings->email_signature,
            'logo' => $this->company->present()->logo(),
        ];


        return (new MailMessage)
                    ->subject(ctrans('texts.notification_payment_paid_subject', ['client' => $this->payment->client->present()->name()]))
                    ->markdown('email.admin.generic', $data);


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

        return (new SlackMessage)
                ->success()
                ->to("#devv2")
                ->from("System")
                ->image($logo)
                ->content("A new account has been created by {$user_name} - {$email} - from IP: {$ip}");
    }

}
