<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Models\Design;
use App\Models\Invoice;
use App\Utils\HtmlEngine;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Support\Facades\Storage;
use App\Services\Template\TemplateService;

class GenerateDeliveryNote
{
    use MakesHash;
    use PdfMaker;

    /**
     * @var mixed
     */
    private $disk;

    public function __construct(private Invoice $invoice, private ?ClientContact $contact = null, $disk = null)
    {
        $this->disk = $disk ?? config('filesystems.default');
    }

    public function run()
    {

        $delivery_note_design_id = $this->invoice->client->getSetting('delivery_note_design_id');
        $design = Design::withTrashed()->find($this->decodePrimaryKey($delivery_note_design_id));

        if ($design && $design->is_template) {

            $ts = new TemplateService($design);

            $pdf = $ts->setCompany($this->invoice->company)
            ->build([
                'invoices' => collect([$this->invoice]),
            ])->getPdf();

            return $pdf;

        }

        $design_id = $this->invoice->design_id
            ? $this->invoice->design_id
            : $this->decodePrimaryKey($this->invoice->client->getSetting('invoice_design_id'));

        $invitation = $this->invoice->invitations->first();

        $design = Design::withTrashed()->find($design_id);

        $ps = new \App\Services\Pdf\PdfService($invitation, 'delivery_note');

        return $ps->boot()->getPdf();

    }
}
