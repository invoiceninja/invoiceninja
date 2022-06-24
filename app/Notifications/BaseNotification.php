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

namespace App\Notifications;

use App\Jobs\Invoice\CreateUbl;
use App\Models\Invoice;
use App\Utils\TempFile;
use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BaseNotification extends Notification
{
    //  use Queueable;
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
     * @return MailMessage
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

        if (strlen($this->settings->reply_to_email) > 1) {
            $mail_message->replyTo($this->settings->reply_to_email);
        }

        if (strlen($this->settings->bcc_email) > 1) {
            $mail_message->bcc($this->settings->bcc_email);
        }

        if ($this->settings->pdf_email_attachment) {
            $mail_message->attach(TempFile::path($this->invitation->pdf_file_path()), ['as' => basename($this->invitation->pdf_file_path())]);
        }

        foreach ($this->entity->documents as $document) {
            $mail_message->attach(TempFile::path($document->generateUrl()), ['as' => $document->name]);
        }

        if ($this->entity instanceof Invoice && $this->settings->ubl_email_attachment) {
            $ubl_string = (new Createubl($this->entity))->handle();
            $mail_message->attachData($ubl_string, $this->entity->getFileName('xml'));
        }

        return $mail_message->withSymfonyMessage(function ($message) {
            $message->getHeaders()->addTextHeader('Tag', $this->invitation->company->company_key);
        });
    }

    public function buildMailMessageData() :array
    {
        $body = $this->generateEmailEntityHtml($this->entity, $this->body, $this->contact);

        $design_style = $this->settings->email_style;

        if ($design_style == 'custom') {
            $email_style_custom = $this->settings->email_style_custom;
            $body = strtr($email_style_custom, "$body", $body);
        }

        $data = [
            'body' => $body,
            'design' => $design_style,
            'footer' => '',
            'title' => '',
            'company' => '',
            'view_link' => $this->invitation->getLink(),
            'view_text' => ctrans('texts.view_'.$this->entity_string),
            'logo' => $this->entity->company->present()->logo(),
            'signature' => $this->settings->email_signature,
            'settings' => $this->settings,
            'whitelabel' => $this->entity->company->account->isPaid() ? true : false,
        ];

        return $data;
    }

    public function getTemplateView()
    {
        switch ($this->settings->email_style) {
            case 'plain':
                return 'email.template.plain';
                break;
            case 'custom':
                return  'email.template.custom';
                break;
            case 'light':
                return  'email.template.light';
                break;
            case 'dark':
                return  'email.template.dark';
                break;
            default:
                return 'email.admin.generic_email';
                break;
        }
    }
}
