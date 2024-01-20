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

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;

class DownloadDocuments extends Mailable
{
    public function __construct(public string $file_path, public Company $company)
    {
    }

    /**
     * Build the message.
     */
    public function build()
    {
        App::setLocale($this->company->getLocale());

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.download_files'))
            ->text('email.admin.download_documents_text', [
                'url' => $this->file_path,
            ])
            ->view('email.admin.download_documents', [
                'url' => $this->file_path,
                'logo' => $this->company->present()->logo,
                'whitelabel' => $this->company->account->isPaid() ? true : false,
                'settings' => $this->company->settings,
                'greeting' => $this->company->present()->name(),
            ]);
    }
}
