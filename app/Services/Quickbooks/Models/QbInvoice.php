<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Models;

use App\Models\Invoice;
use App\Factory\InvoiceFactory;
use App\Interfaces\SyncInterface;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;

class QbInvoice implements SyncInterface
{
    protected InvoiceTransformer $transformer;

    public function __construct(public QuickbooksService $service)
    {
        $this->transformer = new InvoiceTransformer($this->service->company);
    }

    public function find(int $id): mixed
    {
        return $this->service->sdk->FindById('Invoice', $id);
    }

    public function syncToNinja(array $records): void
    {

        foreach ($records as $record) {

            $ninja_invoice_data = $this->transformer->qbToNinja($record);

            $payment_ids = $ninja_invoice_data['payment_ids'] ?? [];

            $client_id = $ninja_invoice_data['client_id'] ?? null;

            if (is_null($client_id)) {
                continue;
            }

            unset($ninja_invoice_data['payment_ids']);

            if ($invoice = $this->findInvoice($ninja_invoice_data)) {
                $invoice->fill($ninja_invoice_data);
                $invoice->saveQuietly();

                $invoice = $invoice->calc()->getInvoice()->service()->markSent()->createInvitations()->save();

                foreach ($payment_ids as $payment_id) {

                    $payment = $this->service->sdk->FindById('Payment', $payment_id);

                    $payment_transformer = new PaymentTransformer($this->service->company);

                    $transformed = $payment_transformer->qbToNinja($payment);

                    $ninja_payment = $payment_transformer->buildPayment($payment);
                    $ninja_payment->service()->applyNumber()->save();

                    $paymentable = new \App\Models\Paymentable();
                    $paymentable->payment_id = $ninja_payment->id;
                    $paymentable->paymentable_id = $invoice->id;
                    $paymentable->paymentable_type = 'invoices';
                    $paymentable->amount = $transformed['applied'] + $ninja_payment->credits->sum('amount');
                    $paymentable->created_at = $ninja_payment->date; //@phpstan-ignore-line
                    $paymentable->save();

                    $invoice->service()->applyPayment($ninja_payment, $paymentable->amount);

                }

                if ($record instanceof IPPSalesReceipt) {
                    $invoice->service()->markPaid()->save();
                }

            }

            $ninja_invoice_data = false;

        }

    }

    public function syncToForeign(array $records): void
    {

    }


    private function findInvoice(array $ninja_invoice_data): ?Invoice
    {
        $search = Invoice::query()
                            ->withTrashed()
                            ->where('company_id', $this->service->company->id)
                            ->where('sync->qb_id', $ninja_invoice_data['id']);

        if($search->count() == 0) {
            $invoice = InvoiceFactory::create($this->service->company->id, $this->service->company->owner()->id);
            $invoice->client_id = $ninja_invoice_data['client_id'];

            return $invoice;
        } elseif($search->count() == 1) {
            return $this->service->settings->invoice->update_record ? $search->first() : null;
        }

        return null;

    }

}
