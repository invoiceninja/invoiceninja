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

namespace App\Mail\Ninja;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class EmailQuotaExceeded extends Mailable
{
    public $company;

    public $settings;

    public $logo;

    public $title;

    public $body;

    public $whitelabel;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($company)
    {
        $this->company = $company;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::setLocale($this->company->getLocale());

        $this->settings = $this->company->settings;
        $this->logo = $this->company->present()->logo();
        $this->title = ctrans('texts.email_quota_exceeded_subject');
        $this->body = ctrans('texts.email_quota_exceeded_body', ['quota' => $this->company->account->getDailyEmailLimit()]);
        $this->whitelabel = $this->company->account->isPaid();
        $this->replyTo('contact@invoiceninja.com', 'Contact');

        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject(ctrans('texts.email_quota_exceeded_subject'))
                    ->view('email.admin.email_quota_exceeded');
    }
}
