<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
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
use ZipArchive;

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
     * @return void
     * @throws \ZipStream\Exception\FileNotFoundException
     * @throws \ZipStream\Exception\FileNotReadableException
     * @throws \ZipStream\Exception\OverflowException
     */
    
    public function handle()
    {
        # create new zip object
        $zip = new ZipArchive();

        $invitation = $this->invoices->first()->invitations->first();
        $path = $this->invoices->first()->client->invoice_filepath($invitation);
        $file_name = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.invoices')).'.zip';
        
        $tmp_file = @tempnam('.', '');
        $zip->open($tmp_file , ZipArchive::OVERWRITE);

        # loop through each file
        foreach ($this->invoices as $invoice) {
            
            $inv = $invoice->invitations->first();

            # download file
            $download_file = file_get_contents($invoice->pdf_file_path($inv, 'url', true));

            #add it to the zip
            $zip->addFromString(basename($invoice->pdf_file_path($inv)), $download_file);
        }

        # close zip
        $zip->close();
        
        Storage::put($path.$file_name, file_get_contents($tmp_file));

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new DownloadInvoices(Storage::url($path.$file_name), $this->company);
        $nmo->to_user = $this->user;
        $nmo->settings = $this->settings;
        $nmo->company = $this->company;
        
        NinjaMailerJob::dispatch($nmo);
        
        UnlinkFile::dispatch(config('filesystems.default'), $path.$file_name)->delay(now()->addHours(1));
    }
}
