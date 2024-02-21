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

class AdminEmailMailable extends Mailable
{
    public int $max_attachment_size = 3000000;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public EmailObject $email_object)
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
            subject: str_replace("<br>", "", $this->email_object->subject),
            tags: [$this->email_object->company_key],
            replyTo: $this->email_object->reply_to,
            from: $this->email_object->from,
            to: $this->email_object->to,
            bcc: $this->email_object->bcc,
            cc: $this->email_object->cc,
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
            view: 'email.admin.generic',
            text: 'email.admin.generic_text',
            with: [
                'title' => $this->email_object->subject,
                'message' => $this->email_object->body,
                'url' => $this->email_object->url ?? null,
                'button' => $this->email_object->button ?? null,
                'signature' => $this->email_object->company->owner()->signature,
                'logo' => $this->email_object->company->present()->logo(),
                'settings' => $this->email_object->settings,
                'whitelabel' => $this->email_object->company->account->isPaid() ? true : false,
                'template' => $this->email_object->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
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

        $attachments = collect($this->email_object->attachments)->map(function ($file) {
            return Attachment::fromData(fn () => base64_decode($file['file']), $file['name']);
        });

        return $attachments->toArray();
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
