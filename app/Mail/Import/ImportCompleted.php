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

namespace App\Mail\Import;

use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class ImportCompleted extends Mailable
{
    // use Queueable, SerializesModels;

    /** @var Company */
    public $company;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $data;

    public function __construct(Company $company, $data)
    {
        $this->company = $company;

        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        App::forgetInstance('translator');
        App::setLocale($this->company->getLocale());

        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $data = array_merge($this->data, [
            'logo' => $this->company->present()->logo(),
            'settings' => $this->company->settings,
            'company' => $this->company,
        ]);

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('email.import.completed', $data);
    }
}
