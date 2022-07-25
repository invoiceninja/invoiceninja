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

namespace App\Jobs\Invoice;

use App\Jobs\Entity\EmailEntity;
use App\Models\Invoice;
use CleverIt\UBL\Invoice\Address;
use CleverIt\UBL\Invoice\Contact;
use CleverIt\UBL\Invoice\Country;
use CleverIt\UBL\Invoice\Generator;
use CleverIt\UBL\Invoice\Invoice as UBLInvoice;
use CleverIt\UBL\Invoice\InvoiceLine;
use CleverIt\UBL\Invoice\Item;
use CleverIt\UBL\Invoice\LegalMonetaryTotal;
use CleverIt\UBL\Invoice\Party;
use CleverIt\UBL\Invoice\TaxCategory;
use CleverIt\UBL\Invoice\TaxScheme;
use CleverIt\UBL\Invoice\TaxSubTotal;
use CleverIt\UBL\Invoice\TaxTotal;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BulkInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    public $reminder_template;

    public function __construct(Invoice $invoice, string $reminder_template)
    {
        $this->invoice = $invoice;
        $this->reminder_template = $reminder_template;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        $this->invoice->service()->touchReminder($this->reminder_template)->markSent()->save();

        $this->invoice->invitations->load('contact.client.country', 'invoice.client.country', 'invoice.company')->each(function ($invitation) {
            EmailEntity::dispatch($invitation, $this->invoice->company, $this->reminder_template)->delay(now()->addSeconds(5));
        });

        if ($this->invoice->invitations->count() >= 1) {
            $this->invoice->entityEmailEvent($this->invoice->invitations->first(), 'invoice', $this->reminder_template);
        }
    }
}
