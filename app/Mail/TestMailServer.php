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

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMailServer extends Mailable
{
    use Queueable, SerializesModels;

    public $message;

    public $from_email;

    public function __construct($message, $from_email)
    {
        $this->message = $message;
        $this->from_email = $from_email;
    }

    /**
     * Test Server mail.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->from_email) //todo this needs to be fixed to handle the hosted version
            ->subject(ctrans('texts.email'))
            ->markdown('email.support.message', [
                'message' => $this->message,
                'system_info' => '',
                'laravel_log' => [],
            ]);
    }
}
