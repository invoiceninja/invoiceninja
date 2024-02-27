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

namespace App\Mail\Client;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ClientStatement extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public array $data)
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
            subject: $this->data['subject'],
            tags: [$this->data['company_key']],
            replyTo: $this->data['reply_to'],
            from: $this->data['from'],
            to: $this->data['to'],
            bcc: $this->data['bcc']
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
            view: $this->data['company']->account->isPremium() ? 'email.template.client_premium' : 'email.template.client',
            text: 'email.template.text',
            with: [
                'text_body' => $this->data['body'],
                'body' => $this->data['body'],
                'settings' => $this->data['settings'],
                'whitelabel' => $this->data['whitelabel'],
                'logo' => $this->data['logo'],
                'signature' => $this->data['signature'],
                'company' => $this->data['company'],
                'greeting' => $this->data['greeting'],
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
        $array_of_attachments = [];

        foreach ($this->data['attachments'] as $attachment) {
            $array_of_attachments[] =
                    Attachment::fromData(fn () => base64_decode($attachment['file']), $attachment['name'])
                              ->withMime('application/pdf');
        }

        return $array_of_attachments;
    }
}
