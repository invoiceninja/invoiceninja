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

use App\Events\Invoice\InvoiceWasCancelled;
use App\Jobs\Ninja\TransactionLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use stdClass;

class HandleCancellation extends AbstractService
{
    use GeneratesCounter;

    private $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {
        /* Check again!! */
        if (! $this->invoice->invoiceCancellable($this->invoice)) {
            return $this->invoice;
        }

        $adjustment = $this->invoice->balance * -1;

        $this->backupCancellation($adjustment);

        //set invoice balance to 0
        $this->invoice->ledger()->updateInvoiceBalance($adjustment, "Invoice {$this->invoice->number} cancellation");

        $this->invoice->balance = 0;
        $this->invoice = $this->invoice->service()->setStatus(Invoice::STATUS_CANCELLED)->save();

        //adjust client balance
        $this->invoice->client->service()->updateBalance($adjustment)->save();
        $this->invoice->fresh();

        $this->invoice->service()->workFlow()->save();

        event(new InvoiceWasCancelled($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        $transaction = [
            'invoice' => $this->invoice->transaction_event(),
            'payment' => [],
            'client' => $this->invoice->client->transaction_event(),
            'credit' => [],
            'metadata' => [],
        ];

        TransactionLog::dispatch(TransactionEvent::INVOICE_CANCELLED, $transaction, $this->invoice->company->db);

        return $this->invoice;
    }

    public function reverse()
    {
        /* The stored cancelled object - contains the adjustment and status*/
        $cancellation = $this->invoice->backup->cancellation;

        /* Will turn the negative cancellation amount to a positive adjustment*/
        $adjustment = $cancellation->adjustment * -1;

        $this->invoice->ledger()->updateInvoiceBalance($adjustment, "Invoice {$this->invoice->number} reversal");
        $this->invoice->fresh();

        /* Reverse the invoice status and balance */
        $this->invoice->balance += $adjustment;
        $this->invoice->status_id = $cancellation->status_id;

        $this->invoice->client->service()->updateBalance($adjustment)->save();

        /* Pop the cancellation out of the backup*/
        $backup = $this->invoice->backup;
        unset($backup->cancellation);
        $this->invoice->backup = $backup;
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
        if (! is_object($this->invoice->backup)) {
            $backup = new stdClass;
            $this->invoice->backup = $backup;
        }

        $cancellation = new stdClass;
        $cancellation->adjustment = $adjustment;
        $cancellation->status_id = $this->invoice->status_id;

        $invoice_backup = $this->invoice->backup;
        $invoice_backup->cancellation = $cancellation;

        $this->invoice->backup = $invoice_backup;
        $this->invoice->saveQuietly();
    }
}
