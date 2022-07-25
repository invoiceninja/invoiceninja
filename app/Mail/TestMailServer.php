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

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMailServer extends Mailable
{
    //   use Queueable, SerializesModels;

    public $support_messages;

    public $from_email;

    public function __construct($support_messages, $from_email)
    {
        $this->support_messages = $support_messages;
        $this->from_email = $from_email;
    }

    /**
     * Test Server mail.
     *
     * @return $this
     */
    public function build()
    {
        $settings = new \stdClass;
        $settings->primary_color = '#4caf50';
        $settings->email_style = 'dark';

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.email'))
            ->markdown('email.support.message', [
                'support_message' => $this->support_messages,
                'system_info' => '',
                'laravel_log' => [],
                'settings' => $settings,
            ]);
    }
}
