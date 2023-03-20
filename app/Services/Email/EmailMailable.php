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

use App\Models\Document;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

class EmailMailable extends Mailable
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
        $links = Document::whereIn('id', $this->email_object->documents)
                ->where('size', '>', $this->max_attachment_size)
                ->cursor()
                ->map(function ($document) {
                    return "<a class='doc_links' href='" . URL::signedRoute('documents.public_download', ['document_hash' => $document->hash]) ."'>". $document->name ."</a>";
                });

        return new Content(
            view: $this->email_object->html_template,
            text: $this->email_object->text_template,
            with: [
                'text_body' => strip_tags($this->email_object->body), //@todo this is a bit hacky here.
                'body' => $this->email_object->body,
                'settings' => $this->email_object->settings,
                'whitelabel' => $this->email_object->whitelabel,
                'logo' => $this->email_object->logo,
                'signature' => $this->email_object->signature,
                'company' => $this->email_object->company,
                'greeting' => '',
                'links' => array_merge($this->email_object->links, $links->toArray()),
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

        $documents = Document::whereIn('id', $this->email_object->documents)
                ->where('size', '<', $this->max_attachment_size)
                ->cursor()
                ->map(function ($document) {
                    return Attachment::fromData(fn () => $document->getFile(), $document->name);
                });

        return $attachments->merge($documents)->toArray();
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
