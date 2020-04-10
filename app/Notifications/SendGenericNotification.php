<?php

namespace App\Notifications;

use App\Utils\Number;
use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGenericNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use MakesInvoiceHtml;
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    
    protected $invitation;
    
    protected $entity;

    protected $contact;

    protected $entity_string;

    protected $settings;

    public    $is_system;

    protected $body;

    protected $subject;

    public function __construct($invitation, $entity_string, $subject, $body)
    {
        $this->entity = $invitation->{$entity_string};
        $this->contact = $invitation->contact;
        $this->settings = $this->entity->client->getMergedSettings();
        $this->subject = $subject;
        $this->body = $body;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
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
        $subject = $this->generateEmailEntityHtml($this->entity, $this->subject, $this->contact);
        $body = $this->generateEmailEntityHtml($this->entity, $this->body, $this->contact);

        $design_style = $this->settings->email_style;

        if ($design_style == 'custom') {
            $email_style_custom = $this->settings->email_style_custom;
            $body = str_replace("$body", $body, $email_style_custom);
        }

        $data = [
            'body' => $body,
            'design' => $design_style,
            'footer' => '',
            'title' => '',
            'settings' => '',
            'company' => '',
            'logo' => $this->entity->company->present()->logo(),
            'signature' => '',

        ];

        $mail_message = (new MailMessage)
                    ->subject($subject)
                    ->markdown('email.admin.generic_email', $data);

        if(strlen($this->settings->reply_to_email) > 1)
            $mail_message->replyTo($this->settings->reply_to_email);

        if(strlen($this->settings->bcc_email) > 1)
            $mail_message->bcc($this->settings->bcc_email);

        if($this->settings->pdf_email_attachment)
            $mail_message->attach(public_path($this->entity->pdf_file_path()));

        foreach($this->entity->documents as $document){
            $mail_message->attach($document->generateUrl());
        }

        return $mail_message;

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
        return '';
        // $logo = $this->company->present()->logo();
        // $amount = Number::formatMoney($this->invoice->amount, $this->invoice->client);

        // return (new SlackMessage)
        //         ->success()
        //         ->from(ctrans('texts.notification_bot'))
        //         ->image($logo)
        //         ->content(ctrans(
        //             'texts.notification_invoice_viewed',
        //             [
        //             'amount' => $amount,
        //             'client' => $this->contact->present()->name(),
        //             'invoice' => $this->invoice->number
        //         ]
        //         ));
    }
}
