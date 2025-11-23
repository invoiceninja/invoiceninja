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

use App\Events\Invoice\InvoiceWasCancelled;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use stdClass;

class HandleCancellation extends AbstractService
{
    use GeneratesCounter;
    use MakesHash;

    public function __construct(private Invoice $invoice, private ?string $reason = null)
    {
    }

    public function run()
    {
        /* Check again!! */
        if (! $this->invoice->invoiceCancellable($this->invoice)) {
            return $this->invoice;
        }

        if ($this->invoice->verifactuEnabled()) {
            return $this->verifactuCancellation();
        }

        $adjustment = ($this->invoice->balance < 0) ? abs($this->invoice->balance) : $this->invoice->balance * -1;

        $this->backupCancellation($adjustment);

        //set invoice balance to 0
        $this->invoice->ledger()->updateInvoiceBalance($adjustment, "Invoice {$this->invoice->number} cancellation");

        $this->invoice->balance = 0;
        $this->invoice = $this->invoice->service()->setStatus(Invoice::STATUS_CANCELLED)->save();

        // $this->invoice->client->service()->updateBalance($adjustment)->save();
        $this->invoice->client->service()->calculateBalance();

        $this->invoice->service()->workFlow()->save();

        event(new InvoiceWasCancelled($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        event('eloquent.updated: App\Models\Invoice', $this->invoice);

        return $this->invoice;
    }


    /**
     * verifactuCancellation
     * @todo we must ensure that if there have been previous credit notes attached to the invoice,
     *       that the credit notes are not exceeded by the cancellation amount.
     *       This is because the credit notes are not linked to the invoice, but are linked to the
     *       invoice's backup.
     *       So we need to check the backup for the credit notes and ensure that the cancellation amount
     *       does not exceed the credit notes.
     *       If it does, we need to create a new credit note with the remaining amount.
     *       This is because the credit notes are not linked to the invoice, but are linked to the
     * @return Invoice
     */
    private function verifactuCancellation(): Invoice
    {

        $this->invoice = $this->invoice->service()->setStatus(Invoice::STATUS_CANCELLED)->save();
        $this->invoice->service()->workFlow()->save();

        // R2 Cancellation - do not create a separate document
        if (in_array($this->invoice->backup->document_type, ['R1','R2'])) { // You cannot cancel a cancellation!!!!!
        } else {
            $replicated_invoice = $this->invoice->replicate();
            unset($replicated_invoice->backup);
            $replicated_invoice->status_id = Invoice::STATUS_DRAFT;
            $replicated_invoice->date = now()->format('Y-m-d');
            $replicated_invoice->due_date = null;
            $replicated_invoice->partial = 0;
            $replicated_invoice->partial_due_date = null;
            $replicated_invoice->number = null;
            $replicated_invoice->amount = 0;
            $replicated_invoice->balance = 0;
            $replicated_invoice->paid_to_date = 0;

            $replicated_invoice->custom_surcharge1 = $this->invoice->custom_surcharge1 * -1;
            $replicated_invoice->custom_surcharge2 = $this->invoice->custom_surcharge2 * -1;
            $replicated_invoice->custom_surcharge3 = $this->invoice->custom_surcharge3 * -1;
            $replicated_invoice->custom_surcharge4 = $this->invoice->custom_surcharge4 * -1;

            $items = $replicated_invoice->line_items;

            foreach ($items as &$item) {
                $item->quantity = $item->quantity * -1;
            }

            $replicated_invoice->line_items = $items;
            $replicated_invoice->backup->parent_invoice_id = $this->invoice->hashed_id;
            $replicated_invoice->backup->parent_invoice_number = $this->invoice->number;
            $replicated_invoice->backup->document_type = 'R2'; // Full Credit Note Generated for the invoice
            $replicated_invoice->backup->notes = $this->reason;

            $invoice_repository = new InvoiceRepository();
            $replicated_invoice = $invoice_repository->save([], $replicated_invoice);
            $replicated_invoice->service()->markSent()->sendVerifactu()->save();

            $this->invoice->backup->child_invoice_ids->push($replicated_invoice->hashed_id);

            $this->invoice->saveQuietly();
        }

        $this->invoice->fresh();

        event(new InvoiceWasCancelled($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        event('eloquent.updated: App\Models\Invoice', $this->invoice);

        return $this->invoice;
    }

    public function reverse()
    {
        /* Will turn the negative cancellation amount to a positive adjustment*/

        $cancellation = $this->invoice->backup->cancellation;
        $adjustment = $cancellation->adjustment * -1;

        $this->invoice->ledger()->updateInvoiceBalance($adjustment, "Invoice {$this->invoice->number} reversal");

        $this->invoice = $this->invoice->fresh();

        /* Reverse the invoice status and balance */
        $this->invoice->balance += $adjustment;
        $this->invoice->status_id = $cancellation->status_id;

        $this->invoice->client->service()->updateBalance($adjustment)->save();

        $this->invoice->client->service()->calculateBalance();

        /* Clear the cancellation data */
        $this->invoice->backup->cancellation->adjustment = 0;
        $this->invoice->backup->cancellation->status_id = 0;
        $this->invoice->saveQuietly();
        $this->invoice->fresh();

        return $this->invoice;
    }

    /**
     * Backup the cancellation in case we ever need to reverse it.
     *
     * @param  float $adjustment  The amount the balance has been reduced by to cancel the invoice
     * @return void
     */
    private function backupCancellation($adjustment)
    {

        // Direct assignment to properties
        $this->invoice->backup->cancellation->adjustment = $adjustment;
        $this->invoice->backup->cancellation->status_id = $this->invoice->status_id;

        $this->invoice->saveQuietly();
    }
}
