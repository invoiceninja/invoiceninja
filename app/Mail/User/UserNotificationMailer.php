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

namespace App\Mail\User;

use Illuminate\Mail\Mailable;

class UserNotificationMailer extends Mailable
{
    public $mail_obj;

    /**
     * Create a new message instance.
     *
     * @param $mail_obj
     */
    public function __construct($mail_obj)
    {
        $this->mail_obj = $mail_obj;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($this->mail_obj->subject)
            ->text('email.admin.generic_text', [
                'title' => $this->mail_obj->data['title'],
                'body' => $this->mail_obj->data['message'],
            ])
            ->view($this->mail_obj->markdown, $this->mail_obj->data)
            ->withSymfonyMessage(function ($message) {
                $message->getHeaders()->addTextHeader('Tag', $this->mail_obj->tag);
            });
    }
}
