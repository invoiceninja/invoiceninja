<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Events\Payment\PaymentWasCreated;
use App\Factory\CreditFactory;
use App\Jobs\Credit\ApplyCreditPayment;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\CreditRepository;
use Illuminate\Http\Request;

/**
 * PaymentRepository
 */
class PaymentRepository extends BaseRepository
{

    protected $credit_repo;

    public function __construct(CreditRepository $credit_repo)
    {
        $this->credit_repo = $credit_repo;
    }

    public function getClassName()
    {
        return Payment::class;
    }

    /**
     * Saves and updates a payment. //todo refactor to handle refunds and payments.
     *
     *
     * @param array $data the request object
     * @param Payment $payment The Payment object
     * @return Payment|null Payment $payment
     */
    public function save(array $data, Payment $payment): ?Payment
    {

        if ($payment->amount >= 0)
            return $this->applyPayment($data, $payment);

        return $this->refundPayment($data, $payment);

    }

    /**
     * Handles a positive payment request
     * @param array $data The data object
     * @param Payment $payment The $payment entity
     * @return Payment          The updated/created payment object
     */
    private function applyPayment(array $data, Payment $payment): ?Payment
    {
        //check currencies here and fill the exchange rate data if necessary
        if(!$payment->id)
            $this->processExchangeRates($data, $payment);

        $payment->fill($data);

        $payment->status_id = Payment::STATUS_COMPLETED;

        $payment->save();

        if (!$payment->number || strlen($payment->number) == 0)
            $payment->number = $payment->client->getNextPaymentNumber($payment->client);

        $payment->client->service()->updatePaidToDate($payment->amount)->save();

        $invoice_totals = 0;
        $credit_totals = 0;

        if (array_key_exists('invoices', $data) && is_array($data['invoices'])) {

            $invoice_totals = array_sum(array_column($data['invoices'], 'amount'));

            $invoices = Invoice::whereIn('id', array_column($data['invoices'], 'invoice_id'))->get();

            $payment->invoices()->saveMany($invoices);

            foreach ($data['invoices'] as $paid_invoice) {
                $invoice = Invoice::whereId($paid_invoice['invoice_id'])->first();

                if ($invoice) {
                    $invoice->service()->applyPayment($payment, $paid_invoice['amount'])->save();
                }
            }
        } else {
            //payment is made, but not to any invoice, therefore we are applying the payment to the clients credit
            $payment->client->processUnappliedPayment($payment->amount);
        }

        if (array_key_exists('credits', $data) && is_array($data['credits'])) {

            $credit_totals = array_sum(array_column($data['credits'], 'amount'));

            $credits = Credit::whereIn('id', array_column($data['credits'], 'credit_id'))->get();

            $payment->credits()->saveMany($credits);

            foreach ($data['credits'] as $paid_credit) {
                $credit = Credit::whereId($paid_credit['credit_id'])->first();

                if ($credit)
                    ApplyCreditPayment::dispatchNow($credit, $payment, $paid_credit['amount'], $credit->company);
            }

        }

        event(new PaymentWasCreated($payment, $payment->company));

        $invoice_totals -= $credit_totals;

        //$payment->amount = $invoice_totals; //creates problems when setting amount like this.

        if ($invoice_totals == $payment->amount)
            $payment->applied += $payment->amount;
        elseif ($invoice_totals < $payment->amount)
            $payment->applied += $invoice_totals;

        $payment->save();

        return $payment->fresh();

    }

    /**
     * @deprecated Refundable trait replaces this.
     */
    private function refundPayment(array $data, Payment $payment): string
    {
        // //temp variable to sum the total refund/credit amount
        // $invoice_total_adjustment = 0;

        // if (array_key_exists('invoices', $data) && is_array($data['invoices'])) {

        //     foreach ($data['invoices'] as $adjusted_invoice) {

        //         $invoice = Invoice::whereId($adjusted_invoice['invoice_id'])->first();

        //         $invoice_total_adjustment += $adjusted_invoice['amount'];

        //         if (array_key_exists('credits', $adjusted_invoice)) {

        //             //process and insert credit notes
        //             foreach ($adjusted_invoice['credits'] as $credit) {

        //                 $credit = $this->credit_repo->save($credit, CreditFactory::create(auth()->user()->id, auth()->user()->id), $invoice);

        //             }

        //         } else {
        //             //todo - generate Credit Note for $amount on $invoice - the assumption here is that it is a FULL refund
        //         }

        //     }

        //     if (array_key_exists('amount', $data) && $data['amount'] != $invoice_total_adjustment)
        //         return 'Amount must equal the sum of invoice adjustments';
        // }


        // //adjust applied amount
        // $payment->applied += $invoice_total_adjustment;

        // //adjust clients paid to date
        // $client = $payment->client;
        // $client->paid_to_date += $invoice_total_adjustment;

        // $payment->save();
        // $client->save();

    }


    /**
     * If the client is paying in a currency other than 
     * the company currency, we need to set a record
     */
    private function processExchangeRates($data, $payment)
    {
        $client = Client::find($data['client_id']);

        $client_currency = $client->getSetting('currency_id');
        $company_currency = $client->company->settings->currency_id;

        if($company_currency != $client_currency)
        {
            $currency = $client->currency();

            $payment->exchange_rate = $currency->exchange_rate;
            $payment->exchange_currency_id = $client_currency;
        }

        return $payment;
    }

}
