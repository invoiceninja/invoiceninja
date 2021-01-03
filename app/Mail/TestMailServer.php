<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
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
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.email'))
            ->markdown('email.support.message', [
                'message' => $this->message,
                'system_info' => '',
                'laravel_log' => [],
            ]);
    }
}
