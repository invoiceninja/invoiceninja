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

namespace App\Jobs\PurchaseOrder;

use App\Jobs\Entity\CreateEntityPdf;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\UnlinkFile;
use App\Jobs\Vendor\CreatePurchaseOrderPdf;
use App\Libraries\MultiDB;
use App\Mail\DownloadInvoices;
use App\Mail\DownloadPurchaseOrders;
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
use ZipArchive;

class ZipPurchaseOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $purchase_orders;

    private $company;

    private $user;

    public $settings;

    public $tries = 1;

    /**
     * @param $purchase_orders
     * @param Company $company
     * @param $email
     * @deprecated confirm to be deleted
     * Create a new job instance.
     */
    public function __construct($purchase_orders, Company $company, User $user)
    {
        $this->purchase_orders = $purchase_orders;

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
        MultiDB::setDb($this->company->db);

        // create new zip object
        $zipFile = new \PhpZip\ZipFile();
        $file_name = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.invoices')).'.zip';
        $invitation = $this->purchase_orders->first()->invitations->first();
        $path = $this->purchase_orders->first()->vendor->purchase_order_filepath($invitation);

        $this->purchase_orders->each(function ($purchase_order) {
            (new CreatePurchaseOrderPdf($purchase_order->invitations()->first()))->handle();
        });

        try {
            foreach ($this->purchase_orders as $purchase_order) {
                $file = $purchase_order->service()->getPurchaseOrderPdf();
                $zip_file_name = basename($file);
                $zipFile->addFromString($zip_file_name, Storage::get($file));
            }

            Storage::put($path.$file_name, $zipFile->outputAsString());

            $nmo = new NinjaMailerObject;
            $nmo->mailable = new DownloadPurchaseOrders(Storage::url($path.$file_name), $this->company);
            $nmo->to_user = $this->user;
            $nmo->settings = $this->settings;
            $nmo->company = $this->company;

            NinjaMailerJob::dispatch($nmo);

            UnlinkFile::dispatch(config('filesystems.default'), $path.$file_name)->delay(now()->addHours(1));
        } catch (\PhpZip\Exception\ZipException $e) {
            nlog('could not make zip => '.$e->getMessage());
        } finally {
            $zipFile->close();
        }
    }
}
