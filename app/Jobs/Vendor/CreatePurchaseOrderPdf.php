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

namespace App\Jobs\Vendor;

use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Vendor;
use App\Models\Account;
use App\Models\Invoice;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use App\Utils\Traits\Pdf\PDF;
use Illuminate\Bus\Queueable;
use App\Models\QuoteInvitation;
use App\Utils\Traits\MakesHash;
use App\Utils\VendorHtmlEngine;
use App\Models\CreditInvitation;
use App\Models\RecurringInvoice;
use App\Services\Pdf\PdfService;
use App\Utils\PhantomJS\Phantom;
use App\Models\InvoiceInvitation;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use App\Utils\Traits\NumberFormatter;
use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Queue\SerializesModels;
use App\Models\PurchaseOrderInvitation;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use setasign\Fpdi\PdfParser\StreamReader;
use App\Exceptions\FilePermissionsFailure;
use App\Models\RecurringInvoiceInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;

class CreatePurchaseOrderPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $entity;

    private $disk;

    public PurchaseOrderInvitation $invitation;

    public Vendor $vendor;
    /**
     * Create a new job instance.
     *
     * @param $invitation
     */
    public function __construct($invitation, $disk = null)
    {
        $this->invitation = $invitation;
        
        $this->entity = $invitation->purchase_order;

        $this->vendor = $invitation->contact->vendor;
        
        $this->disk = $disk ?? config('filesystems.default');

    }

    public function handle()
    {

        MultiDB::setDb($this->invitation->company->db);

        $file_path = $this->vendor->purchase_order_filepath($this->invitation);

        $pdf = (new PdfService($this->invitation, 'purchase_order'))->getPdf();

        if ($pdf) {
            try {
                Storage::disk($this->disk)->put($file_path, $pdf);
            } catch (\Exception $e) {
                throw new FilePermissionsFailure($e->getMessage());
            }
        }
        
        return $pdf;

    }

    public function failed($e)
    {

    }
    

}
