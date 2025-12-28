<?php

/**
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
            ->view($this->mail_obj->markdown, $this->mail_obj->data);
    }
}
