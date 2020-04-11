<?php

namespace App\Notifications;

use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use MakesInvoiceHtml;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
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

    public function buildMailMessageSettings(MailMessage $mail_message) :MailMessage
    {
        $mail_message->subject($this->generateEmailEntityHtml($this->entity, $this->subject, $this->contact));

        if(strlen($this->settings->reply_to_email) > 1)
            $mail_message->replyTo($this->settings->reply_to_email);

        if(strlen($this->settings->bcc_email) > 1)
            $mail_message->bcc($this->settings->bcc_email);

        if($this->settings->pdf_email_attachment)
            $mail_message->attach(public_path($this->entity->pdf_file_path()));

        foreach($this->entity->documents as $document){
            $mail_message->attach($document->generateUrl(), ['as' => $document->name]);
        }

        if($this->entity instanceof Invoice && $this->settings->ubl_email_attachment){
            $ubl_string = CreateUbl::dispatchNow($this->entity);
            $mail_message->attachData($ubl_string, $this->entity->getFileName('xml'));
        }


        return $mail_message;
    }

    public function buildMailMessageData() :array
    {

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

        return $data;

    }
}
