<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Invoice;

use App\Jobs\Mail\BaseMailerJob;
use App\Jobs\Util\UnlinkFile;
use App\Libraries\MultiDB;
use App\Mail\DownloadInvoices;
use App\Models\Company;
use App\Models\Invoice;
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

class ZipInvoices extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoices;

    private $company;

    private $email;

    public $settings;

    /**
     * @param $invoices
     * @param Company $company
     * @param $email
     * @deprecated confirm to be deleted
     * Create a new job instance.
     *
     */
    public function __construct($invoices, Company $company, $email)
    {
        $this->invoices = $invoices;

        $this->company = $company;

        $this->email = $email;

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

        $path = $this->invoices->first()->client->invoice_filepath();

        $zip = new ZipStream($file_name, $options);

        foreach ($this->invoices as $invoice) {
            $zip->addFileFromPath(basename($invoice->pdf_file_path()), TempFile::path($invoice->pdf_file_path()));
        }

        $zip->finish();

        Storage::disk(config('filesystems.default'))->put($path.$file_name, $tempStream);

        fclose($tempStream);

        $this->setMailDriver();

        try {
            Mail::to($this->email)
                ->send(new DownloadInvoices(Storage::disk(config('filesystems.default'))->url($path.$file_name), $this->company));
        }
        catch (\Exception $e) {
            $this->failed($e);

        }
        
        UnlinkFile::dispatch(config('filesystems.default'), $path.$file_name)->delay(now()->addHours(1));
    }
}
