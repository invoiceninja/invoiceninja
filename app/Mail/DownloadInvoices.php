<?php

namespace App\Mail;

use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DownloadInvoices extends Mailable
{
    use Queueable, SerializesModels;

    public $file_path;

    public $company;

    public function __construct($file_path, Company $company)
    {
        $this->file_path = $file_path;

        $this->company = $company;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject(ctrans('texts.download_files'))
                    ->markdown(
                        'email.admin.download_files',
                        [
                            'url' => $this->file_path,
                            'logo' => $this->company->present()->logo,
                        ]
                    );

    }
}
