<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Ninja\Mailers\UserMailer;
use Barracuda\ArchiveStream\Archive;

/**
 * Class SendInvoiceEmail.
 */
//class DownloadInvoices extends Job implements ShouldQueue
class DownloadInvoices extends Job
{
    //use InteractsWithQueue, SerializesModels;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var array
     */
    protected $invoices;

    /**
     * Create a new job instance.
     *
     * @param mixed   $files
     * @param mixed   $settings
     */
    public function __construct(User $user, $invoices)
    {
        $this->user = $user;
        $this->invoices = $invoices;
    }

    /**
     * Execute the job.
     *
     * @param ContactMailer $mailer
     */
    public function handle(UserMailer $userMailer)
    {
        if (! extension_loaded('GMP')) {
            die(trans('texts.gmp_required'));
        }

        $zip = Archive::instance_by_useragent(date('Y-m-d') . '_' . str_replace(' ', '_', trans('texts.invoice_pdfs')));

        foreach ($this->invoices as $invoice) {
            $zip->add_file($invoice->getFileName(), $invoice->getPDFString());
        }

        $zip->finish();
        exit;
    }
}
