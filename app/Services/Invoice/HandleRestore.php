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

use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;

class HandleRestore extends AbstractService
{
    use GeneratesCounter;

    private $invoice;

    private $payment_total = 0;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {
        $this->invoice->restore();

        if (! $this->invoice->is_deleted) {
            return $this->invoice;
        }

        //determine whether we need to un-delete payments OR just modify the payment amount /applied balances.

        foreach ($this->invoice->payments as $payment) {
            //restore the payment record
            $this->invoice->restore();
        }

        //adjust ledger balance
        $this->invoice->ledger()->updateInvoiceBalance($this->invoice->balance, "Restored invoice {$this->invoice->number}")->save();

        $this->invoice->client->service()->updateBalance($this->invoice->balance)->save();

        $this->windBackInvoiceNumber();

        $this->invoice->is_deleted = false;
        $this->invoice->save();

        return $this->invoice;
    }

    private function windBackInvoiceNumber()
    {
        $findme = '_'.ctrans('texts.deleted');

        $pos = strpos($this->invoice->number, $findme);

        $new_invoice_number = substr($this->invoice->number, 0, $pos);

        if (strlen($new_invoice_number) == 0) {
            $new_invoice_number = null;
        }

        try {
            $exists = Invoice::where(['company_id' => $this->invoice->company_id, 'number' => $new_invoice_number])->exists();

            if ($exists) {
                $this->invoice->number = $this->getNextInvoiceNumber($this->invoice->client, $this->invoice, $this->invoice->recurring_id);
            } else {
                $this->invoice->number = $new_invoice_number;
            }

            $this->invoice->saveQuietly();
        } catch (\Exception $e) {
            nlog('I could not wind back the invoice number');

            if (Ninja::isHosted()) {
                \Sentry\captureMessage('I could not wind back the invoice number');
                app('sentry')->captureException($e);
            }
        }
    }
}
