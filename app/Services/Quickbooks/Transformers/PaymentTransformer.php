<?php

/**
 * Invoice Ninja (https://Paymentninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

use App\Models\Credit;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\DataMapper\PaymentSync;
use App\Factory\PaymentFactory;

/**
 *
 * Class PaymentTransformer.
 */
class PaymentTransformer extends BaseTransformer
{
    public function qbToNinja(mixed $qb_data)
    {
        return $this->transform($qb_data);
    }

    public function ninjaToQb()
    {
    }

    public function transform(mixed $qb_data)
    {

        return [
            'id' => data_get($qb_data, 'Id', null),
            'date' => data_get($qb_data, 'TxnDate', now()->format('Y-m-d')),
            'amount' => floatval(data_get($qb_data, 'TotalAmt', 0)),
            'applied' => data_get($qb_data, 'TotalAmt', 0) - data_get($qb_data, 'UnappliedAmt', 0),
            'number' => data_get($qb_data, 'DocNumber', null),
            'private_notes' => data_get($qb_data, 'PrivateNote', null),
            'currency_id' => (string) $this->resolveCurrency(data_get($qb_data, 'CurrencyRef')),
            'client_id' => $this->getClientId(data_get($qb_data, 'CustomerRef', null)),
        ];
    }

    public function associatePaymentToInvoice(Payment $payment, mixed $qb_data)
    {

        $invoice = Invoice::query()
                ->withTrashed()
                ->where('company_id', $this->company->id)
                ->where('sync->qb_id', data_get($qb_data, 'invoice_id'))
                ->first();

        if (!$invoice) {
            return;
        }

        $lines = data_get($qb_data, 'Line', []) ?? [];

        if (!empty($lines) && !isset($lines[0])) {
            $lines = [$lines];
        }

        foreach ($lines as $item) {
            $id = data_get($item, 'LinkedTxn.TxnId', false);
            $tx_type = data_get($item, 'LinkedTxn.TxnType', false);
            $amount = data_get($item, 'Amount', 0);

            if ($tx_type == 'Invoice' && $id == $invoice->sync->qb_id && $amount > 0) {

                $paymentable = new \App\Models\Paymentable();
                $paymentable->payment_id = $payment->id;
                $paymentable->paymentable_id = $invoice->id;
                $paymentable->paymentable_type = 'invoices';
                $paymentable->amount = $amount;
                $paymentable->created_at = $payment->date; //@phpstan-ignore-line
                $paymentable->save();

                $invoice->service()->applyPayment($payment, $paymentable->amount);
                return;
            }
        }

    }


    public function buildPayment($qb_data): ?Payment
    {
        $ninja_payment_data = $this->transform($qb_data);

        $search_payment = Payment::query()
            ->withTrashed()
            ->where('company_id', $this->company->id)
            ->where('sync->qb_id', $ninja_payment_data['id'])
            ->first();

        if ($search_payment) {
            return $search_payment;
        }


        if ($ninja_payment_data['client_id']) {
            $payment = PaymentFactory::create($this->company->id, $this->company->owner()->id, $ninja_payment_data['client_id']);
            $payment->amount = $ninja_payment_data['amount'];
            $payment->applied = $ninja_payment_data['applied'];
            $payment->status_id = 4;

            $sync = new PaymentSync();
            $sync->qb_id = $ninja_payment_data['id'];
            $payment->sync = $sync;

            $payment->fill($ninja_payment_data);
            $payment->save();

            $payment->client->service()->updatePaidToDate($payment->amount);

            if ($payment->amount == 0) {
                //this is a credit memo, create a stub credit for this.
                $payment = $this->createCredit($payment, $qb_data);
                $payment->type_id = \App\Models\PaymentType::CREDIT;
                $payment->save();
            }


            return $payment;

        }
        return null;
    }

    private function createCredit($payment, $qb_data)
    {
        $credit_line = null;

        $credit_array = data_get($qb_data, 'Line', []);

        foreach ($credit_array as $item) {

            if (data_get($item, 'LinkedTxn.TxnType', null) == 'CreditMemo') {
                $credit_line = $item;
                break;
            }

        }

        if (!$credit_line) {
            return $payment;
        }

        $credit = \App\Factory\CreditFactory::create($this->company->id, $this->company->owner()->id);
        $credit->client_id = $payment->client_id;

        $line = new \App\DataMapper\InvoiceItem();
        $line->quantity = 1;
        $line->cost = data_get($credit_line, 'Amount', 0);
        $line->product_key = 'CREDITMEMO';
        $line->notes = $payment->private_notes;

        $credit->date = data_get($qb_data, 'TxnDate', now()->format('Y-m-d'));
        $credit->status_id = 4;
        $credit->amount = data_get($credit_line, 'Amount', 0);
        $credit->paid_to_date = data_get($credit_line, 'Amount', 0);
        $credit->balance = 0;
        $credit->line_items = [$line];
        $credit->save();

        $paymentable = new \App\Models\Paymentable();
        $paymentable->payment_id = $payment->id;
        $paymentable->paymentable_id = $credit->id;
        $paymentable->paymentable_type = \App\Models\Credit::class;
        $paymentable->amount = $credit->amount;
        $paymentable->created_at = $payment->date;
        $paymentable->save();

        return $payment;
    }

    public function getLine($data, $field = null)
    {
        $invoices = [];
        $invoice = $this->getString($data, 'Line.LinkedTxn.TxnType');
        if (is_null($invoice) || $invoice !== 'Invoice') {
            return $invoices;
        }
        if (is_null(($invoice_id = $this->getInvoiceId($this->getString($data, 'Line.LinkedTxn.TxnId'))))) {
            return $invoices;
        }

        return [[
            'amount' => (float) $this->getString($data, 'Line.Amount'),
            'invoice_id' => $invoice_id
        ]];
    }

}
