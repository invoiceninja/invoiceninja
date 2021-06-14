<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Invoice;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\UnlinkFile;
use App\Mail\DownloadInvoices;
use App\Models\Company;
use App\Models\User;
use App\Utils\TempFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class ZipInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoices;

    private $company;

    private $user;

    public $settings;

    /**
     * @param $invoices
     * @param Company $company
     * @param $email
     * @deprecated confirm to be deleted
     * Create a new job instance.
     *
     */
    public function __construct($invoices, Company $company, User $user)
    {
        $this->invoices = $invoices;

        $this->company = $company;

        $this->user = $user;

        $this->settings = $company->settings;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     * @throws \ZipStream\Exception\FileNotFoundException
     * @throws \ZipStream\Exception\FileNotReadableException
     * @throws \ZipStream\Exception\OverflowException
     */
    public function handle()
    {
        $tempStream = fopen('php://memory', 'w+');

        $options = new Archive();
        $options->setOutputStream($tempStream);

        // create a new zipstream object
        $file_name = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.invoices')).'.zip';

        $invoice = $this->invoices->first();
        $invitation = $invoice->invitations->first();

        $path = $invoice->client->invoice_filepath($invitation);

        $zip = new ZipStream($file_name, $options);

        foreach ($this->invoices as $invoice) {
            //$zip->addFileFromPath(basename($invoice->pdf_file_path()), TempFile::path($invoice->pdf_file_path()));
            $zip->addFileFromPath(basename($invoice->pdf_file_path($invitation)), $invoice->pdf_file_path());
        }

        $zip->finish();

        Storage::disk('public')->put($path.$file_name, $tempStream);

        fclose($tempStream);

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new DownloadInvoices(Storage::disk('public')->url($path.$file_name), $this->company);
        $nmo->to_user = $this->user;
        $nmo->settings = $this->settings;
        $nmo->company = $this->company;
        
        NinjaMailerJob::dispatch($nmo);
        
        UnlinkFile::dispatch('public', $path.$file_name)->delay(now()->addHours(1));
    }
}
