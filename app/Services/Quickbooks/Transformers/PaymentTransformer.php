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
use App\Models\PaymentType;
use App\DataMapper\PaymentSync;
use App\Factory\PaymentFactory;
use App\Services\Quickbooks\QuickbooksService;

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

    /**
     * Transform an Invoice Ninja Payment into a QuickBooks Payment payload.
     *
     * @param Payment $payment
     * @param QuickbooksService $qbService
     * @return array
     */
    public function ninjaToQb(Payment $payment, QuickbooksService $qbService): array
    {
        // Resolve client QB ID — auto-create if missing
        $client_qb_id = $payment->client->sync->qb_id ?? null;
        if (!$client_qb_id) {
            $client_qb_id = $qbService->client->createQbClient($payment->client);
        }

        if (!$client_qb_id) {
            throw new \Exception("Cannot push payment {$payment->id}: client has no QuickBooks ID and could not be created");
        }

        // Build Line items from Paymentable records (invoices only)
        $lines = [];
        foreach ($payment->invoices as $invoice) {
            $invoice_qb_id = $invoice->sync->qb_id ?? null;
            if (!$invoice_qb_id) {
                continue;
            }

            $lines[] = [
                'Amount' => (float) $invoice->pivot->amount,
                'LinkedTxn' => [
                    [
                        'TxnId' => $invoice_qb_id,
                        'TxnType' => 'Invoice',
                    ],
                ],
            ];
        }

        // Calculate TotalAmt
        // For credit payments (type_id=32 with amount=0), use the sum of invoice paymentable amounts
        $total_amt = $payment->amount;
        if ($payment->amount <= 0 && !empty($lines)) {
            $total_amt = array_sum(array_column($lines, 'Amount'));
        }

        $payment_data = [
            'CustomerRef' => ['value' => $client_qb_id],
            'TotalAmt' => (float) $total_amt,
            'TxnDate' => $payment->date ?? now()->format('Y-m-d'),
        ];

        if (!empty($lines)) {
            $payment_data['Line'] = $lines;
        }

        if ($payment->private_notes) {
            $payment_data['PrivateNote'] = mb_substr($payment->private_notes, 0, 4000);
        }

        if ($payment->transaction_reference) {
            $payment_data['PaymentRefNum'] = mb_substr($payment->transaction_reference, 0, 21);
        }

        // Resolve PaymentMethodRef from IN type_id
        $payment_method_ref = $this->resolvePaymentMethodRef($payment->type_id);
        if ($payment_method_ref) {
            $payment_data['PaymentMethodRef'] = ['value' => $payment_method_ref];
        }

        // Currency
        if ($payment->currency_id && $payment->currency) {
            $payment_data['CurrencyRef'] = ['value' => $payment->currency->code];
        }

        // Include Id for updates
        if (!empty($payment->sync->qb_id)) {
            $payment_data['Id'] = $payment->sync->qb_id;
        }

        return $payment_data;
    }

    /**
     * Resolve an Invoice Ninja PaymentType ID to a QuickBooks PaymentMethod ID.
     *
     * Uses the cached payment_method_map from QB settings.
     *
     * @param int|null $type_id
     * @return string|null QB PaymentMethod ID
     */
    private function resolvePaymentMethodRef(?int $type_id): ?string
    {
        if (!$type_id) {
            return null;
        }

        $method_map = $this->company->quickbooks->settings->payment_method_map ?? [];

        if (empty($method_map)) {
            return null;
        }

        // Map IN payment type to a QB method name search strategy
        $target_name = match ($type_id) {
            PaymentType::CASH => 'Cash',
            PaymentType::CHECK, PaymentType::MONEY_ORDER => 'Check',
            PaymentType::VISA, PaymentType::MASTERCARD, PaymentType::AMERICAN_EXPRESS,
            PaymentType::DISCOVER, PaymentType::DINERS, PaymentType::EUROCARD,
            PaymentType::NOVA, PaymentType::CREDIT_CARD_OTHER, PaymentType::CARTE_BLANCHE,
            PaymentType::UNIONPAY, PaymentType::JCB, PaymentType::LASER,
            PaymentType::MAESTRO, PaymentType::SOLO, PaymentType::SWITCH,
            PaymentType::DEBIT => 'CREDIT_CARD',
            PaymentType::BANK_TRANSFER, PaymentType::ACH, PaymentType::SEPA,
            PaymentType::DIRECT_DEBIT, PaymentType::BECS, PaymentType::ACSS,
            PaymentType::BACS, PaymentType::STRIPE_BANK_TRANSFER,
            PaymentType::MOLLIE_BANK_TRANSFER => 'Check',
            default => null,
        };

        if (!$target_name) {
            return null;
        }

        // Search for exact name match first
        foreach ($method_map as $method) {
            if (strcasecmp($method['name'] ?? '', $target_name) === 0) {
                return $method['id'];
            }
        }

        // For credit card types, match by Type field
        if ($target_name === 'CREDIT_CARD') {
            foreach ($method_map as $method) {
                if (strcasecmp($method['type'] ?? '', 'CREDIT_CARD') === 0) {
                    return $method['id'];
                }
            }
        }

        return null;
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

        // QB can return a single object or an array; normalize to array
        if (!empty($lines)) {
            if (!is_array($lines)) {
                $lines = [$lines];
            } elseif (!isset($lines[0])) {
                $lines = [$lines];
            }
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

        // QB can return a single object or an array; normalize to array
        if (!empty($credit_array)) {
            if (!is_array($credit_array)) {
                $credit_array = [$credit_array];
            } elseif (!isset($credit_array[0])) {
                $credit_array = [$credit_array];
            }
        } else {
            $credit_array = [];
        }

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
            'invoice_id' => $invoice_id,
        ]];
    }

}
