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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class DownloadReport extends Mailable
{
    use Queueable, SerializesModels;

    protected Company $company;

    protected $csv;

    protected string $file_name;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Company $company, $csv, $file_name)
    {
        $this->company = $company;
        $this->csv = $csv;
        $this->file_name = $file_name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::setLocale($this->company->getLocale());

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.download_files'))
            ->text('email.admin.download_report_text')
            ->attachData($this->csv, $this->file_name, [
                'mime' => 'text/csv',
            ])
            ->view('email.admin.download_report', [
                'logo' => $this->company->present()->logo,
                'whitelabel' => $this->company->account->isPaid() ? true : false,
                'settings' => $this->company->settings,
                'greeting' => $this->company->present()->name(),
            ]);
    }
}
