<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Invoice;

use App\DataMapper\InvoiceSync;
use App\Factory\InvoiceFactory;
use App\Models\Company;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Utils\BcMath;
use App\Utils\Traits\MakesHash;

class InvoiceLookup
{
    use MakesHash;

    public function __construct(
        private Company $company,
        private InvoiceRepository $invoice_repository,
    ) {
    }

    public function findByQbId(string $id, ?string $client_id = null): ?Invoice
    {
        $search = Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->company->id)
            ->where('sync->qb_id', $id);

        if ($search->count() == 0) {
            $invoice = InvoiceFactory::create($this->company->id, $this->company->owner()->id);
            $invoice->client_id = (int) $client_id;
            $invoice->design_id = $this->decodePrimaryKey($this->company->settings->invoice_design_id);

            $sync = new InvoiceSync();
            $sync->markSynced($id);
            $invoice->sync = $sync;

            return $invoice;
        }

        return $search->first();
    }

    public function findByNumber(string $number): ?Invoice
    {
        if ($number === '') {
            return null;
        }

        return Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->company->id)
            ->where('number', $number)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $ninja_invoice_data
     */
    public function updateFromQuickbooks(array $ninja_invoice_data, Invoice $invoice): void
    {
        $current_ninja_invoice_balance = $invoice->balance;
        $qb_invoice_balance = $ninja_invoice_data['balance'];

        if (BcMath::equal($current_ninja_invoice_balance, $qb_invoice_balance)) {
            nlog('Invoice balance is the same, skipping update of line items');
            unset($ninja_invoice_data['line_items']);
            $invoice->fill($ninja_invoice_data);
            $invoice->saveQuietly();
        } else {
            nlog('Invoice balance is different, updating line items');
            $this->invoice_repository->save($ninja_invoice_data, $invoice);
        }
    }
}
