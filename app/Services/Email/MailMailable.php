<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Email;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class MailMailable extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public MailObject $mail_object)
    {
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: $this->mail_object->subject,
            tags: [$this->mail_object->company_key],
            replyTo: $this->mail_object->reply_to,
            from: $this->mail_object->from,
            to: $this->mail_object->to,
            bcc: $this->mail_object->bcc
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: $this->mail_object->html_template,
            text: $this->mail_object->text_template,
            with: [
                'text_body' => str_replace("<br>", "\n", strip_tags($this->mail_object->body, "<br>")), //@todo this is a bit hacky here.
                'body' => $this->mail_object->body,
                'settings' => $this->mail_object->settings,
                'whitelabel' => $this->mail_object->whitelabel,
                'logo' => $this->mail_object->logo,
                'signature' => $this->mail_object->signature,
                'company' => $this->mail_object->company,
                'greeting' => ''
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        $attachments  = [];

        foreach ($this->mail_object->attachments as $file) {
            $attachments[] = Attachment::fromData(fn () => base64_decode($file['file']), $file['name']);
        }

        return $attachments;
    }
 
    /**
     * Get the message headers.
     *
     * @return \Illuminate\Mail\Mailables\Headers
     */
    public function headers()
    {
        return new Headers(
            messageId: null,
            references: [],
            text: $this->mail_object->headers,
        );
    }
}
