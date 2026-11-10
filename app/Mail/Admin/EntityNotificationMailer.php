<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Admin;

use Illuminate\Mail\Mailable;

//@deprecated?
class EntityNotificationMailer extends Mailable
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
        $mailable = $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($this->mail_obj->subject)
            ->view($this->mail_obj->markdown, $this->mail_obj->data);

        if (property_exists($this->mail_obj, 'text_view')) {
            $mailable->text($this->mail_obj->text_view, $this->mail_obj->data);
        }

        return $mailable;
    }
}
