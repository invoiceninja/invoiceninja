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

namespace App\Listeners\Payment;

use Carbon\Carbon;
use App\Utils\BcMath;
use App\Models\Invoice;
use App\Models\Payment;
use App\Libraries\MultiDB;
use App\Models\TransactionEvent;
use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\DataMapper\TransactionEventMetadata;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class PaymentTransactionEventEntry implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public $tries = 1;

    public $delay = 9;

    private float $paid_ratio;

    private float $refund_ratio = 0;

    private Collection $payments;

    /**
     * @param Payment $payment
     * @param array $invoice_ids
     * @param string $db
     * @param mixed $invoice_adjustment - represents the differential amount (which could be variable and never a static known property value)
     * @param bool $is_deleted
     */
    public function __construct(private Payment $payment, private array $invoice_ids, private string $db, private mixed $invoice_adjustment = 0, private bool $is_deleted = false)
    {}

    public function handle()
    {
        
       try{
        $this->runLog();
       }
       catch(\Throwable $e){
        nlog("PaymentTransactionEventEntry::handle - ERROR");
        nlog($e->getMessage());
        nlog($e->getTraceAsString());
       }
    }

    private function runLog()
    {
        //payment vs refunded
        MultiDB::setDb($this->db);

        if($this->payment->invoices()->count() == 0 && !$this->payment->is_deleted){
            nlog("PaymentTransactionEventEntry::runLog:: no invoices found");
            return;
        }
        
        //consider deleted invoices!! the following will not hit.

        $this->payments = $this->payment
                            ->invoices()
                            ->get()
                            ->filter(function($invoice){
                                //only insert adjustment entries if we are after the end of the month!!
                                return Carbon::parse($invoice->date)->endOfMonth()->isBefore(now()->addSeconds($this->payment->company->timezone_offset()));
                            })
                            ->map(function ($invoice) {
                                return [
                                    'number' => $this->payment->number,
                                    'amount' => $invoice->pivot->amount,
                                    'refunded' => $invoice->pivot->refunded,
                                    'date' => $invoice->pivot->created_at->format('Y-m-d'),
                                ];
                        });


        Invoice::withTrashed()
                ->whereIn('id', $this->invoice_ids)
                ->get()
                ->filter(function($invoice){
                    //only insert adjustment entries if we are after the end of the month!!
                    return Carbon::parse($invoice->date)->endOfMonth()->isBefore(now()->addSeconds($this->payment->company->timezone_offset()));
                })
                ->each(function($invoice){
                $this->setPaidRatio($invoice);

                //delete any other payment mutations here if this is a delete event, the refunds are redundant in this time period
                $invoice->transaction_events()
                        ->where('event_id', TransactionEvent::PAYMENT_REFUNDED)
                        ->where('period', now()->endOfMonth()->format('Y-m-d'))
                        ->delete();
                
                TransactionEvent::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'client_balance' => $invoice->client->balance,
                    'client_paid_to_date' => $invoice->client->paid_to_date,
                    'client_credit_balance' => $invoice->client->credit_balance,
                    'invoice_balance' => $invoice->balance ?? 0,
                    'invoice_amount' => $invoice->amount ?? 0  ,
                    'invoice_partial' => $invoice->partial ?? 0,
                    'invoice_paid_to_date' => $invoice->paid_to_date ?? 0,
                    'invoice_status' => $invoice->is_deleted ? 7 : $invoice->status_id,
                    'event_id' => $this->is_deleted ? TransactionEvent::PAYMENT_DELETED : TransactionEvent::PAYMENT_REFUNDED,
                    'timestamp' => now()->timestamp,
                    'metadata' => $this->getMetadata($invoice),
                    'period' => now()->endOfMonth()->format('Y-m-d'),
                    'payment_id' => $this->payment->id,
                    'payment_amount' => $this->payment->amount,
                    'payment_refunded' => $this->payment->refunded,
                    'payment_applied' => $this->payment->applied,
                    'payment_status' => $this->payment->status_id,
                ]);
        });
    }

    private function setPaidRatio(Invoice $invoice): self
    {
        if ($invoice->amount == 0) {
            $this->paid_ratio = 0;
            return $this;
        }

        // For refunds/deletions, the paid_to_date has already been decremented
        // So we need to add back the refund amount to get the PREVIOUS paid_to_date
        $paid_to_date_for_ratio = $invoice->paid_to_date;
        if ($this->invoice_adjustment > 0) {
            $paid_to_date_for_ratio += $this->invoice_adjustment;
        }

        $this->paid_ratio = $paid_to_date_for_ratio / $invoice->amount;

        return $this;
    }

    /**
     * Existing tax details are not deleted, but pending taxes are set to 0
     *
     * For partial refunds, uses pro-rata calculation based on refund amount / invoice amount
     * For full refunds, uses paid_ratio (payment amount / invoice amount)
     *
     * @param  mixed $invoice
     */
    private function getRefundedMetaData($invoice)
    {

        $calc = $invoice->calc();

        $details = [];

        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());

        // Calculate refund ratio: refund_amount / invoice_amount
        // This gives us the pro-rata portion of taxes to refund
        $refund_ratio = $invoice->amount > 0 ? $this->invoice_adjustment / $invoice->amount : 0;

        foreach ($taxes as $tax) {

            $base_amount = $tax['base_amount'] ?? $calc->getNetSubtotal();

            $tax_detail = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => round($base_amount * $refund_ratio, 2) * -1,
                'tax_amount' => round($tax['total'] * $refund_ratio, 2) * -1,
                'line_total' => $base_amount,
                'total_tax' => $tax['total'],
                'postal_code' => $invoice->client->postal_code,
               ];
            $details[] = $tax_detail;
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'payment_history' => $this->payments->toArray(),
                'tax_summary' => [
                    'tax_amount' => round($invoice->total_taxes * $refund_ratio, 2) * -1,
                    'status' => 'adjustment',
                    'taxable_amount' => round($calc->getNetSubtotal() * $refund_ratio, 2) * -1,
                ],
            ],
        ]);

    }

    /**
     * Set all tax details to 0
     *
     * @param  mixed $invoice
     */
    private function getDeletedMetaData($invoice)
    {

        $calc = $invoice->calc();

        $details = [];

        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());

        foreach ($taxes as $tax) {

            $base_amount = $tax['base_amount'] ?? $calc->getNetSubtotal();

            $tax_detail = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => $base_amount * -1,
                'tax_amount' => $tax['total'] * -1,
                'tax_status' => 'payment_deleted',
                'line_total' => $base_amount,
                'total_tax' => $tax['total'],
                'postal_code' => $invoice->client->postal_code,
            ];

            $details[] = $tax_detail;
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'payment_history' => $this->payments->toArray(),
                'tax_summary' => [
                    'tax_amount' => round($invoice->total_taxes - $this->getTotalTaxPaid($invoice), 2) * -1,
                    'taxable_amount' => $calc->getNetSubtotal() * -1,
                    'status' => 'adjustment',
                ],
            ],
        ]);

    }

    private function getMetadata($invoice)
    {

        if ($this->payment->is_deleted) {
            return $this->getDeletedMetaData($invoice);
        } else {
            return $this->getRefundedMetaData($invoice);
        }

    }

    private function getTotalTaxPaid($invoice)
    {
        if ((int)$invoice->amount == 0) {
            return 0;
        }

        $total_paid = $this->payments->sum('amount') - $this->payments->sum('refunded');

        // nlog("total paid => {$total_paid} - total taxes => {$invoice->total_taxes} - amount => {$invoice->amount}");

        return round($invoice->total_taxes * ($total_paid / $invoice->amount), 2);

    }

    public function middleware()
    {
        return [(new WithoutOverlapping("payment_transaction_event_entry_".$this->payment->id.'_'.$this->db))->dontRelease()];
    }

    public function failed(?\Throwable $exception)
    {
        nlog("PaymentTransactionEventEntry::failed");

        if(!$exception)
            return;
        
        nlog($exception->getMessage());
    }
}
