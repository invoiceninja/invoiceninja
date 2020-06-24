<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentRefundFailed;
use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Activity;
use App\Models\CompanyGateway;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\ActivityRepository;

class RefundPayment
{
    public $payment;

    public $refund_data;

    private $credit_note;

    private $total_refund;

    private $gateway_refund_status;

    private $activity_repository;

    public function __construct($payment, $refund_data)
    {
        $this->payment = $payment;

        $this->refund_data = $refund_data;

        $this->total_refund = 0;

        $this->gateway_refund_status = false;

        $this->activity_repository = new ActivityRepository();
    }

    /**
     */
    public function run()
    {

        return $this->calculateTotalRefund() //sets amount for the refund (needed if we are refunding multiple invoices in one payment)
            ->setStatus() //sets status of payment
            ->buildCreditNote() //generate the credit note
            ->buildCreditLineItems() //generate the credit note items
            ->updateCreditables() //return the credits first
            ->updatePaymentables() //update the paymentable items
            ->adjustInvoices()
            ->createActivity() // create the refund activity
            ->processGatewayRefund() //process the gateway refund if needed
            ->save();
    }

    private function processGatewayRefund()
    {
        // if ($this->refund_data['gateway_refund'] !== false && $this->total_refund > 0) {
        if (true) {

            $gateway = CompanyGateway::first();

            if ($gateway) {
                $response = $gateway->driver($this->payment->client)->refund($this->payment, $this->total_refund);

                if ($response['success']) {
                    throw new PaymentRefundFailed();
                }

                $this->payment->refunded = $this->total_refund;

                $activity = [
                    'payment_id' => $this->payment->id,
                    'user_id' => $this->payment->user->id,
                    'company_id' => $this->payment->company->id,
                    'activity_type_id' => Activity::REFUNDED_PAYMENT,
                    'credit_id' => 1, // ???
                    'notes' => $response,
                ];

                /** Persist activiy to database. */
                // $this->activity_repository->save($activity, ??);

                /** Substract credit amount from the refunded value. */
            }
        } else {
            $this->payment->refunded += $this->total_refund;
        }

        return $this;
    }

    private function createActivity()
    {
        $fields = new \stdClass;
        $activity_repo = new ActivityRepository();

        $fields->payment_id = $this->payment->id;
        $fields->user_id = $this->payment->user_id;
        $fields->company_id = $this->payment->company_id;
        $fields->activity_type_id = Activity::REFUNDED_PAYMENT;
        $fields->credit_id = $this->credit_note->id;

        if (isset($this->refund_data['invoices'])) {
            foreach ($this->refund_data['invoices'] as $invoice) {
                $fields->invoice_id = $invoice['invoice_id'];
                $activity_repo->save($fields, $this->payment);
            }
        } else {
            $activity_repo->save($fields, $this->payment);
        }

        return $this;
    }

    private function calculateTotalRefund()
    {

        if (isset($this->refund_data['invoices']) && count($this->refund_data['invoices']) > 0)
            $this->total_refund = collect($this->refund_data['invoices'])->sum('amount');
        else
            $this->total_refund = $this->refund_data['amount'];

        return $this;
    }

    private function setStatus()
    {
        if ($this->refund_data['amount'] == $this->payment->amount) {
            $this->payment->status_id = Payment::STATUS_REFUNDED;
        } else {
            $this->payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        }

        return $this;
    }

    private function buildCreditNote()
    {
        $this->credit_note = CreditFactory::create($this->payment->company_id, $this->payment->user_id);
        $this->credit_note->assigned_user_id = isset($this->payment->assigned_user_id) ?: null;
        $this->credit_note->date = $this->refund_data['date'];
        $this->credit_note->status_id = Credit::STATUS_SENT;
        $this->credit_note->client_id = $this->payment->client->id;
        $this->credit_note->amount = $this->refund_data['amount'];
        $this->credit_note->balance = $this->refund_data['amount'];

        $this->credit_note->save();
        $this->credit_note->number = $this->payment->client->getNextCreditNumber($this->payment->client);
        $this->credit_note->save();

        return $this;
    }

    private function buildCreditLineItems()
    {
        $ledger_string = '';

        if (isset($this->refund_data['invoices']) && count($this->refund_data['invoices']) > 0) {
            foreach ($this->refund_data['invoices'] as $invoice) {

                $inv = Invoice::find($invoice['invoice_id']);

                $credit_line_item = InvoiceItemFactory::create();
                $credit_line_item->quantity = 1;
                $credit_line_item->cost = $invoice['amount'];
                $credit_line_item->product_key = ctrans('texts.invoice');
                $credit_line_item->notes = ctrans('texts.refund_body', ['amount' => $invoice['amount'], 'invoice_number' => $inv->number]);
                $credit_line_item->line_total = $invoice['amount'];
                $credit_line_item->date = $this->refund_data['date'];

                $ledger_string .= $credit_line_item->notes . ' ';

                $line_items[] = $credit_line_item;
            }
        } else {

            $credit_line_item = InvoiceItemFactory::create();
            $credit_line_item->quantity = 1;
            $credit_line_item->cost = $this->refund_data['amount'];
            $credit_line_item->product_key = ctrans('texts.credit');
            $credit_line_item->notes = ctrans('texts.credit_created_by', ['transaction_reference' => $this->payment->number]);
            $credit_line_item->line_total = $this->refund_data['amount'];
            $credit_line_item->date = $this->refund_data['date'];

            $line_items = [];
            $line_items[] = $credit_line_item;
        }

        $this->credit_note->line_items = $line_items;
        $this->credit_note->save();

        return $this;
    }

    private function updatePaymentables()
    {
        if (isset($this->refund_data['invoices']) && count($this->refund_data['invoices']) > 0) {
            $this->payment->invoices->each(function ($paymentable_invoice) {

                collect($this->refund_data['invoices'])->each(function ($refunded_invoice) use ($paymentable_invoice) {

                    if ($refunded_invoice['invoice_id'] == $paymentable_invoice->id) {
                        $paymentable_invoice->pivot->refunded += $refunded_invoice['amount'];
                        $paymentable_invoice->pivot->save();
                    }
                });
            });
        }

        return $this;
    }

    private function updateCreditables()
    {

        if ($this->payment->credits()->exists()) {
            //Adjust credits first!!!
            foreach ($this->payment->credits as $paymentable_credit) {
                $available_credit = $paymentable_credit->pivot->amount - $paymentable_credit->pivot->refunded;

                if ($available_credit > $this->total_refund) {
                    $paymentable_credit->pivot->refunded += $this->total_refund;
                    $paymentable_credit->pivot->save();

                    $paymentable_credit->balance += $this->total_refund;
                    $paymentable_credit->save();

                    $this->total_refund = 0;
                } else {
                    $paymentable_credit->pivot->refunded += $available_credit;
                    $paymentable_credit->pivot->save();

                    $paymentable_credit->balance += $available_credit;
                    $paymentable_credit->save();

                    $this->total_refund -= $available_credit;
                }

                if ($this->total_refund == 0) {
                    break;
                }
            }
        }

        return $this;
    }


    private function adjustInvoices()
    {
        $adjustment_amount = 0;

        if (isset($this->refund_data['invoices']) && count($this->refund_data['invoices']) > 0) {
            foreach ($this->refund_data['invoices'] as $refunded_invoice) {
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

            $ledger_string = ''; //todo

            $this->credit_note->ledger()->updateCreditBalance($adjustment_amount, $ledger_string);

            $this->payment->client->paid_to_date -= $this->refund_data['amount'];
            $this->payment->client->save();
        }

        return $this;
    }

    private function save()
    {
        $this->payment->save();

        return $this->payment;
    }
}





/*

    private function refundPaymentWithNoInvoices(array $data)
    {

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

        if ($data['gateway_refund'] !== false && $this->total_refund > 0) {
            $gateway = CompanyGateway::find($this->company_gateway_id);

            if ($gateway) {
                $response = $gateway->driver($this->client)->refund($this, $this->total_refund);

                if (!$response) {
                    throw new PaymentRefundFailed();
                }
            }
        }

        if ($this->total_refund > 0) {
            $this->refunded += $this->total_refund;
        }

        $this->save();

        $client_balance_adjustment = $this->adjustInvoices($data);

        $credit_note->ledger()->updateCreditBalance($client_balance_adjustment, $ledger_string);

        $this->client->paid_to_date -= $data['amount'];
        $this->client->save();


        return $this;
    }

    private function createActivity(array $data, int $credit_id)
    {
        $fields = new \stdClass;
        $activity_repo = new ActivityRepository();

        $fields->payment_id = $this->id;
        $fields->user_id = $this->user_id;
        $fields->company_id = $this->company_id;
        $fields->activity_type_id = Activity::REFUNDED_PAYMENT;
        $fields->credit_id = $credit_id;

        if (isset($data['invoices'])) {
            foreach ($data['invoices'] as $invoice) {
                $fields->invoice_id = $invoice->id;
                
                $activity_repo->save($fields, $this);
            }
        } else {
            $activity_repo->save($fields, $this);
        }
    }


    private function buildCreditNote(array $data) :?Credit
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

 */