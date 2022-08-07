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

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class DownloadBackup extends Mailable
{
    public $file_path;

    public $company;

    public function __construct($file_path, Company $company)
    {
        $this->file_path = $file_path;

        $this->company = $company;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        App::setLocale($this->company->getLocale());

        $company = Company::where('company_key', $this->company->company_key)->first();

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.download_backup_subject', ['company' => $company->present()->name()]))
            ->text('email.admin.download_files_text', [
                'url' => $this->file_path,
            ])
            ->view('email.admin.download_files', [
                'url' => $this->file_path,
                'logo' => $company->present()->logo,
                'whitelabel' => $company->account->isPaid() ? true : false,
                'settings' => $company->settings,
                'greeting' => $company->present()->name(),
            ]);
    }
}
