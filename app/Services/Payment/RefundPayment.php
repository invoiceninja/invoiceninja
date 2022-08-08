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

namespace App\Services\Payment;

use App\Exceptions\PaymentRefundFailed;
use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Jobs\Ninja\TransactionLog;
use App\Jobs\Payment\EmailRefundPayment;
use App\Models\Activity;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TransactionEvent;
use App\Repositories\ActivityRepository;
use App\Utils\Ninja;
use stdClass;

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

    public function run()
    {
        $this->payment = $this->calculateTotalRefund() //sets amount for the refund (needed if we are refunding multiple invoices in one payment)
            ->setStatus() //sets status of payment
            //->reversePayment()
            //->buildCreditNote() //generate the credit note
            //->buildCreditLineItems() //generate the credit note items
            ->updateCreditables() //return the credits first
            ->updatePaymentables() //update the paymentable items
            ->adjustInvoices()
            ->processGatewayRefund() //process the gateway refund if needed
            ->save();

        if (array_key_exists('email_receipt', $this->refund_data) && $this->refund_data['email_receipt'] == 'true') {
            $contact = $this->payment->client->contacts()->whereNotNull('email')->first();
            EmailRefundPayment::dispatch($this->payment, $this->payment->company, $contact);
        }

        $transaction = [
            'invoice' => [],
            'payment' => $this->payment->transaction_event(),
            'client' => $this->payment->client->transaction_event(),
            'credit' => [],
            'metadata' => [],
        ];

        TransactionLog::dispatch(TransactionEvent::PAYMENT_REFUND, $transaction, $this->payment->company->db);

        return $this->payment;
    }

    /**
     * Process the refund through the gateway.
     *
     * @return $this
     * @throws PaymentRefundFailed
     */
    private function processGatewayRefund()
    {
        if ($this->refund_data['gateway_refund'] !== false && $this->total_refund > 0) {
            if ($this->payment->company_gateway) {
                $response = $this->payment->company_gateway->driver($this->payment->client)->refund($this->payment, $this->total_refund);

                $this->payment->refunded += $this->total_refund;

                $this->createActivity($this->payment);

                if ($response['success'] == false) {
                    $this->payment->save();

                    if (array_key_exists('description', $response)) {
                        throw new PaymentRefundFailed($response['description']);
                    } else {
                        throw new PaymentRefundFailed();
                    }
                }
            }
        } else {
            $this->payment->refunded += $this->total_refund;
        }

        return $this;
    }

    /**
     * Create the payment activity.
     *
     * @param  json $notes gateway_transaction
     * @return $this
     */
    private function createActivity($notes)
    {
        $fields = new stdClass;
        $activity_repo = new ActivityRepository();

        $fields->payment_id = $this->payment->id;
        $fields->user_id = $this->payment->user_id;
        $fields->company_id = $this->payment->company_id;
        $fields->activity_type_id = Activity::REFUNDED_PAYMENT;
        // $fields->credit_id = $this->credit_note->id; // TODO
        $fields->notes = json_encode($notes);

        if (isset($this->refund_data['invoices'])) {
            foreach ($this->refund_data['invoices'] as $invoice) {
                $fields->invoice_id = $invoice['invoice_id'];
                $activity_repo->save($fields, $this->payment, Ninja::eventVars(auth()->user() ? auth()->user()->id : null));
            }
        } else {
            $activity_repo->save($fields, $this->payment, Ninja::eventVars(auth()->user() ? auth()->user()->id : null));
        }

        return $this;
    }

    /**
     * Determine the amount of refund.
     *
     * @return $this
     */
    private function calculateTotalRefund()
    {
        if (array_key_exists('invoices', $this->refund_data) && count($this->refund_data['invoices']) > 0) {
            $this->total_refund = collect($this->refund_data['invoices'])->sum('amount');
        } else {
            $this->total_refund = $this->refund_data['amount'];
        }

        return $this;
    }

    /**
     * Set the payment status.
     */
    private function setStatus()
    {
        if ($this->total_refund == $this->payment->amount) {
            $this->payment->status_id = Payment::STATUS_REFUNDED;
        } else {
            $this->payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        }

        return $this;
    }

    /**
     * Update the paymentable records.
     *
     * @return $this
     */
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

    /**
     * If credits have been bundled in this payment, we
     * need to reverse these.
     *
     * @return $this
     */
    private function updateCreditables()
    {
        if ($this->payment->credits()->exists()) {
            //Adjust credits first!!!
            foreach ($this->payment->credits as $paymentable_credit) {
                $available_credit = $paymentable_credit->pivot->amount - $paymentable_credit->pivot->refunded;

                if ($available_credit > $this->total_refund) {
                    $paymentable_credit->pivot->refunded += $this->total_refund;
                    $paymentable_credit->pivot->save();

                    $paymentable_credit->service()
                                       ->setStatus(Credit::STATUS_SENT)
                                       ->updateBalance($this->total_refund)
                                       ->updatePaidToDate($this->total_refund * -1)
                                       ->save();

                    $this->total_refund = 0;
                } else {
                    $paymentable_credit->pivot->refunded += $available_credit;
                    $paymentable_credit->pivot->save();

                    $paymentable_credit->balance += $available_credit;
                    $paymentable_credit->service()
                                      ->setStatus(Credit::STATUS_SENT)
                                       ->adjustBalance($available_credit)
                                       ->updatePaidToDate($available_credit * -1)
                                      ->save();

                    $this->total_refund -= $available_credit;
                }

                if ($this->total_refund == 0) {
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Reverse the payments made on invoices.
     *
     * @return $this
     */
    private function adjustInvoices()
    {
        $adjustment_amount = 0;

        if (isset($this->refund_data['invoices']) && count($this->refund_data['invoices']) > 0) {
            foreach ($this->refund_data['invoices'] as $refunded_invoice) {
                $invoice = Invoice::withTrashed()->find($refunded_invoice['invoice_id']);

                if ($invoice->trashed()) {
                    $invoice->restore();
                }

                $invoice->service()
                        ->updateBalance($refunded_invoice['amount'])
                        ->updatePaidToDate($refunded_invoice['amount'] * -1)
                        ->save();

                $invoice->ledger()->updateInvoiceBalance($refunded_invoice['amount'], "Refund of payment # {$this->payment->number}")->save();

                if ($invoice->amount == $invoice->balance) {
                    $invoice->service()->setStatus(Invoice::STATUS_SENT);
                } else {
                    $invoice->service()->setStatus(Invoice::STATUS_PARTIAL);
                }

                $invoice->saveQuietly();

                $client = $invoice->client;
                $adjustment_amount += $refunded_invoice['amount'];
                $client->balance += $refunded_invoice['amount'];
                $client->save();

                $transaction = [
                    'invoice' => $invoice->transaction_event(),
                    'payment' => [],
                    'client' => $client->transaction_event(),
                    'credit' => [],
                    'metadata' => [],
                ];

                TransactionLog::dispatch(TransactionEvent::PAYMENT_REFUND, $transaction, $invoice->company->db);

                if ($invoice->is_deleted) {
                    $invoice->delete();
                }
            }

            $client = $this->payment->client->fresh();

            if ($client->trashed()) {
                $client->restore();
            }

            $client->service()->updatePaidToDate(-1 * $refunded_invoice['amount'])->save();

            $transaction = [
                'invoice' => [],
                'payment' => [],
                'client' => $client->transaction_event(),
                'credit' => [],
                'metadata' => [],
            ];

            TransactionLog::dispatch(TransactionEvent::PAYMENT_REFUND, $transaction, $client->company->db);
        } else {
            //if we are refunding and no payments have been tagged, then we need to decrement the client->paid_to_date by the total refund amount.

            $client = $this->payment->client->fresh();

            if ($client->trashed()) {
                $client->restore();
            }

            $client->service()->updatePaidToDate(-1 * $this->total_refund)->save();

            $transaction = [
                'invoice' => [],
                'payment' => [],
                'client' => $client->transaction_event(),
                'credit' => [],
                'metadata' => [],
            ];

            TransactionLog::dispatch(TransactionEvent::PAYMENT_REFUND, $transaction, $client->company->db);
        }

        return $this;
    }

    /**
     * Saves the payment.
     *
     * @return Payment $payment
     */
    private function save()
    {
        $this->payment->save();

        return $this->payment;
    }

    // public function updateCreditNoteBalance()
    // {
    //     $this->credit_note->balance -= $this->total_refund;
    //     $this->credit_note->status_id = Credit::STATUS_APPLIED;

    //     $this->credit_note->balance === 0
    //         ? $this->credit_note->status_id = Credit::STATUS_APPLIED
    //         : $this->credit_note->status_id = Credit::STATUS_PARTIAL;

    //     $this->credit_note->save();

    //     return $this;
    // }

    // private function buildCreditNote()
    // {
    //     $this->credit_note = CreditFactory::create($this->payment->company_id, $this->payment->user_id);
    //     $this->credit_note->assigned_user_id = isset($this->payment->assigned_user_id) ?: null;
    //     $this->credit_note->date = $this->refund_data['date'];
    //     $this->credit_note->status_id = Credit::STATUS_SENT;
    //     $this->credit_note->client_id = $this->payment->client->id;
    //     $this->credit_note->amount = $this->total_refund;
    //     $this->credit_note->balance = $this->total_refund;

    //     $this->credit_note->save();
    //     $this->credit_note->number = $this->payment->client->getNextCreditNumber($this->payment->client);
    //     $this->credit_note->save();

    //     return $this;
    // }

    // private function buildCreditLineItems()
    // {
    //     $ledger_string = '';

    //     if (isset($this->refund_data['invoices']) && count($this->refund_data['invoices']) > 0) {
    //         foreach ($this->refund_data['invoices'] as $invoice) {

    //             $inv = Invoice::find($invoice['invoice_id']);

    //             $credit_line_item = InvoiceItemFactory::create();
    //             $credit_line_item->quantity = 1;
    //             $credit_line_item->cost = $invoice['amount'];
    //             $credit_line_item->product_key = ctrans('texts.invoice');
    //             $credit_line_item->notes = ctrans('texts.refund_body', ['amount' => $invoice['amount'], 'invoice_number' => $inv->number]);
    //             $credit_line_item->line_total = $invoice['amount'];
    //             $credit_line_item->date = $this->refund_data['date'];

    //             $ledger_string .= $credit_line_item->notes . ' ';

    //             $line_items[] = $credit_line_item;
    //         }
    //     } else {

    //         $credit_line_item = InvoiceItemFactory::create();
    //         $credit_line_item->quantity = 1;
    //         $credit_line_item->cost = $this->refund_data['amount'];
    //         $credit_line_item->product_key = ctrans('texts.credit');
    //         $credit_line_item->notes = ctrans('texts.credit_created_by', ['transaction_reference' => $this->payment->number]);
    //         $credit_line_item->line_total = $this->refund_data['amount'];
    //         $credit_line_item->date = $this->refund_data['date'];

    //         $line_items = [];
    //         $line_items[] = $credit_line_item;
    //     }

    //     $this->credit_note->line_items = $line_items;
    //     $this->credit_note->save();

    //     return $this;
    // }
}
