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

namespace App\Mail;

use App\Exceptions\ClientHostedMigrationException;
use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;

class MigrationFailed extends Mailable
{
    public $exception;

    public $content;

    public $company;

    public $is_system = false;

    /**
     * Create a new message instance.
     *
     * @param $content
     * @param $exception
     */
    public function __construct($exception, Company $company, $content = null)
    {
        $this->exception = $exception;
        $this->content = $content;
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

        $special_message = '';

        if ($this->exception instanceof ClientHostedMigrationException) {
            $special_message = $this->content;
        }

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->text('email.migration.failed_text')
            ->view('email.migration.failed', [
                'special_message' => $special_message,
                'logo' => $this->company->present()->logo(),
                'settings' => $this->company->settings,
                'is_system' => $this->is_system,
            ]);
    }
}
