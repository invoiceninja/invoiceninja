<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits\Payment;

use App\Exceptions\PaymentRefundFailed;
use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Activity;
use App\Models\CompanyGateway;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\ActivityRepository;
use App\Utils\Ninja;
use stdClass;

trait Refundable
{
    /**
     * Entry point for processing of refunds.
     * @param array $data
     * @deprecated ???? 06-09-2022
     * @return self
     * @throws PaymentRefundFailed
     */
    public function processRefund(array $data)
    {
        if (isset($data['invoices']) && count($data['invoices']) > 0) {
            return $this->refundPaymentWithInvoices($data);
        }

        return $this->refundPaymentWithNoInvoices($data);
    }

    private function refundPaymentWithNoInvoices(array $data)
    {
        //adjust payment refunded column amount
        $this->refunded = $data['amount'];

        if ($data['amount'] == $this->amount) {
            $this->status_id = Payment::STATUS_REFUNDED;
        } else {
            $this->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        }

        $credit_note = $this->buildCreditNote($data);

        $credit_line_item = InvoiceItemFactory::create();
        $credit_line_item->quantity = 1;
        $credit_line_item->cost = $data['amount'];
        $credit_line_item->product_key = ctrans('texts.credit');
        $credit_line_item->notes = ctrans('texts.credit_created_by', ['transaction_reference' => $this->number]);
        $credit_line_item->line_total = $data['amount'];
        $credit_line_item->date = $data['date'];

        $line_items = [];
        $line_items[] = $credit_line_item;

        $credit_note->save();
        $credit_note->number = $this->client->getNextCreditNumber($this->client, $credit_note);
        $credit_note->save();

        $this->createActivity($data, $credit_note->id);

        //determine if we need to refund via gateway
        if ($data['gateway_refund'] !== false) {
            //todo process gateway refund, on success, reduce the credit note balance to 0
        }

        $this->save();

        //$this->client->paid_to_date -= $data['amount'];
        $this->client->save();

        return $this->fresh();
    }

    private function refundPaymentWithInvoices($data)
    {
        $total_refund = 0;

        foreach ($data['invoices'] as $invoice) {
            $total_refund += $invoice['amount'];
        }

        $data['amount'] = $total_refund;

        /* Set Payment Status*/
        if ($total_refund == $this->amount) {
            $this->status_id = Payment::STATUS_REFUNDED;
        } else {
            $this->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        }

        /* Build Credit Note*/
        $credit_note = $this->buildCreditNote($data);

        $line_items = [];

        $ledger_string = '';

        foreach ($data['invoices'] as $invoice) {
            /** @var \App\Models\Invoice $inv */
            $inv = Invoice::find($invoice['invoice_id']);

            $credit_line_item = InvoiceItemFactory::create();
            $credit_line_item->quantity = 1;
            $credit_line_item->cost = $invoice['amount'];
            $credit_line_item->product_key = ctrans('texts.invoice');
            $credit_line_item->notes = ctrans('texts.refund_body', ['amount' => $data['amount'], 'invoice_number' => $inv->number]);
            $credit_line_item->line_total = $invoice['amount'];
            $credit_line_item->date = $data['date'];

            $ledger_string .= $credit_line_item->notes.' ';

            $line_items[] = $credit_line_item;
        }

        /* Update paymentable record */
        foreach ($this->invoices as $paymentable_invoice) {
            foreach ($data['invoices'] as $refunded_invoice) {
                if ($refunded_invoice['invoice_id'] == $paymentable_invoice->id) {
                    $paymentable_invoice->pivot->refunded += $refunded_invoice['amount'];
                    $paymentable_invoice->pivot->save();
                }
            }
        }

        if ($this->credits()->exists()) {
            //Adjust credits first!!!
            foreach ($this->credits as $paymentable_credit) {
                $available_credit = $paymentable_credit->pivot->amount - $paymentable_credit->pivot->refunded;

                if ($available_credit > $total_refund) {
                    $paymentable_credit->pivot->refunded += $total_refund;
                    $paymentable_credit->pivot->save();

                    $paymentable_credit->balance += $total_refund;
                    $paymentable_credit->save();

                    $total_refund = 0;
                } else {
                    $paymentable_credit->pivot->refunded += $available_credit;
                    $paymentable_credit->pivot->save();

                    $paymentable_credit->balance += $available_credit;
                    $paymentable_credit->save();

                    $total_refund -= $available_credit;
                }

                if ($total_refund == 0) {
                    break;
                }
            }
        }

        $credit_note->line_items = $line_items;
        $credit_note->save();

        $credit_note->number = $this->client->getNextCreditNumber($this->client, $credit_note);
        $credit_note->save();

        if ($data['gateway_refund'] !== false && $total_refund > 0) {
            /** @var \App\Models\CompanyGateway $gateway */
            $gateway = CompanyGateway::find($this->company_gateway_id);

            if ($gateway) {
                $response = $gateway->driver($this->client)->refund($this, $total_refund);

                if (! $response) {
                    throw new PaymentRefundFailed();
                }
            }
        }

        if ($total_refund > 0) {
            $this->refunded += $total_refund;
        }

        $this->save();

        $client_balance_adjustment = $this->adjustInvoices($data);

        /** @var \App\Models\Payment $this */
        $this->client->paid_to_date -= $data['amount'];
        $this->client->save();

        return $this;
    }

    private function createActivity(array $data, int $credit_id)
    {
        $fields = new stdClass();
        $activity_repo = new ActivityRepository();

        $fields->payment_id = $this->id;
        $fields->user_id = $this->user_id;
        $fields->company_id = $this->company_id;
        $fields->activity_type_id = Activity::REFUNDED_PAYMENT;
        $fields->credit_id = $credit_id;
        $fields->client_id = $this->client_id;

        if (isset($data['invoices'])) {
            foreach ($data['invoices'] as $invoice) {
                $fields->invoice_id = $invoice->id;

                $activity_repo->save($fields, $this, Ninja::eventVars(auth()->user() ? auth()->user()->id : null));
            }
        } else {
            $activity_repo->save($fields, $this, Ninja::eventVars(auth()->user() ? auth()->user()->id : null));
        }
    }

    private function buildCreditNote(array $data): ?Credit
    {
        $credit_note = CreditFactory::create($this->company_id, $this->user_id);
        $credit_note->assigned_user_id = isset($this->assigned_user_id) ?: null;
        $credit_note->date = $data['date'];
        $credit_note->status_id = Credit::STATUS_SENT;
        $credit_note->client_id = $this->client->id;
        $credit_note->amount = $data['amount'];
        $credit_note->balance = $data['amount'];

        return $credit_note;
    }

    private function adjustInvoices(array $data)
    {
        $adjustment_amount = 0;

        foreach ($data['invoices'] as $refunded_invoice) {
            /** @var \App\Models\Invoice $invoice */
            $invoice = Invoice::find($refunded_invoice['invoice_id']);

            $invoice->service()->updateBalance($refunded_invoice['amount'])->save();

            if ($invoice->amount == $invoice->balance) {
                $invoice->service()->setStatus(Invoice::STATUS_SENT);
            } else {
                $invoice->service()->setStatus(Invoice::STATUS_PARTIAL);
            }

            $client = $invoice->client;

            $adjustment_amount += $refunded_invoice['amount'];
            $client->balance += $refunded_invoice['amount'];

            $client->save();

            //todo adjust ledger balance here? or after and reference the credit and its total
        }

        return $adjustment_amount;
    }
}
