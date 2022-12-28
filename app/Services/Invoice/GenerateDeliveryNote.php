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

namespace App\Services\Invoice;

use App\Models\ClientContact;
use App\Models\Design;
use App\Models\Invoice;
use App\Services\Pdf\PdfService;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Support\Facades\Storage;

class GenerateDeliveryNote
{
    use MakesHash, PdfMaker;

    /**
     * @var \App\Models\Invoice
     */
    private $invoice;

    /**
     * @var \App\Models\ClientContact
     */
    private $contact;

    /**
     * @var mixed
     */
    private $disk;

    public function __construct(Invoice $invoice, ClientContact $contact = null, $disk = null)
    {
        $this->invoice = $invoice;

        $this->contact = $contact;

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function run()
    {

        $invitation = $this->invoice->invitations->first();

        $file_path = sprintf('%sdelivery_note.pdf', $this->invoice->client->invoice_filepath($invitation));

        $pdf = (new PdfService($invitation, 'delivery_note'))->getPdf();

        Storage::disk($this->disk)->put($file_path, $pdf);
        
        return $file_path;

    }
}
