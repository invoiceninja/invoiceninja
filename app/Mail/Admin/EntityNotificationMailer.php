<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Mail\Admin;

use App\Models\User;
use Illuminate\Mail\Mailable;

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
        return $this->from($this->mail_obj->from[0], $this->mail_obj->from[1])
                    ->subject($this->mail_obj->subject)
                    ->markdown($this->mail_obj->markdown, $this->mail_obj->data)
                    ->withSwiftMessage(function ($message) {
                        $message->getHeaders()->addTextHeader('Tag', $this->mail_obj->tag);
                    });
    }
}
