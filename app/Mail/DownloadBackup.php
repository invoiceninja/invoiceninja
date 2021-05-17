<?php

namespace App\Mail;

use App\Models\Company;
use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

class DownloadBackup extends Mailable
{
    // use Queueable, SerializesModels;
    use MakesInvoiceHtml;

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
        $template = File::get(resource_path('views/email/admin/download_files.blade.php'));

        $company = Company::where('company_key', $this->company->company_key)->first();

        $view = $this->renderView($template, [
            'url' => $this->file_path,
            'logo' => $company->present()->logo,
            'whitelabel' => $company->account->isPaid() ? true : false,
            'settings' => $company->settings,
            'greeting' => $company->present()->name(),
        ]);

        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject(ctrans('texts.download_backup_subject', ['company' => $company->present()->name()]))
                    ->html($view);
    }
}
