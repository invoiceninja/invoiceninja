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

namespace App\Services\Email;

use App\Services\Email\EmailObject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Headers;

class EmailMailable extends Mailable
{

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public EmailObject $email_object){}

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: $this->email_object->subject,
            tags: [$this->email_object->company_key],
            replyTo: $this->email_object->reply_to,
            from: $this->email_object->from,
            to: $this->email_object->to,
            bcc: $this->email_object->bcc
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
            view: $this->email_object->template,
            text: $this->email_object->text_template,
            with: [
                'text_body' => strip_tags($this->email_object->body),
                'body' => $this->email_object->body,
                'settings' => $this->email_object->settings,
                'whitelabel' => $this->email_object->whitelabel,
                'logo' => $this->email_object->logo,
                'signature' => $this->email_object->signature,
                'company' => $this->email_object->company,
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

        foreach($this->email_object->attachments as $file)
        {
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
            text: $this->email_object->headers,
        );

    }

}
