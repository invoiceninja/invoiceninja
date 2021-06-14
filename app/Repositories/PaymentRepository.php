<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Events\Payment\PaymentWasCreated;
use App\Events\Payment\PaymentWasDeleted;
use App\Jobs\Credit\ApplyCreditPayment;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * PaymentRepository.
 */
class PaymentRepository extends BaseRepository {
	use MakesHash;
	use SavesDocuments;

	protected $credit_repo;

	public function __construct( CreditRepository $credit_repo ) {
		$this->credit_repo = $credit_repo;
	}

	/**
	 * Saves and updates a payment. //todo refactor to handle refunds and payments.
	 *
	 * @param array   $data the request object
     * @param Payment $payment The Payment object
     * @return Payment|null Payment $payment
     */
    public function save(array $data, Payment $payment): ?Payment
    {
        if ($payment->amount >= 0) {
            return $this->applyPayment($data, $payment);
        }

        return $payment;
    }

    /**
     * Handles a positive payment request.
     * @param  array $data      The data object
     * @param  Payment $payment The $payment entity
     * @return Payment          The updated/created payment object
     */
    private function applyPayment(array $data, Payment $payment): ?Payment
    {

        $is_existing_payment = true;

        //check currencies here and fill the exchange rate data if necessary
        if (! $payment->id) {
            $this->processExchangeRates($data, $payment);

            $is_existing_payment = false;
            $client = Client::where('id', $data['client_id'])->withTrashed()->first();

            /*We only update the paid to date ONCE per payment*/
            if (array_key_exists('invoices', $data) && is_array($data['invoices']) && count($data['invoices']) > 0) {
                if ($data['amount'] == '') {
                    $data['amount'] = array_sum(array_column($data['invoices'], 'amount'));
                }

                $client->service()->updatePaidToDate($data['amount'])->save();
            }

            if (array_key_exists('credits', $data) && is_array($data['credits']) && count($data['credits']) > 0) {
                $_credit_totals = array_sum(array_column($data['credits'], 'amount'));

                if ($data['amount'] == $_credit_totals) {
                    $data['amount'] = 0;
                } else {
                    $client->service()->updatePaidToDate($_credit_totals)->save();
                }
            }
        }

        /*Fill the payment*/
        $payment->fill($data);
        $payment->is_manual = true;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->save();

        /*Save documents*/
        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $payment);
        }

        /*Ensure payment number generated*/
        if (! $payment->number || strlen($payment->number) == 0) {
            $payment->number = $payment->client->getNextPaymentNumber($payment->client);
        }

        /*Set local total variables*/
        $invoice_totals = 0;
        $credit_totals = 0;

        /*Iterate through invoices and apply payments*/
        if (array_key_exists('invoices', $data) && is_array($data['invoices']) && count($data['invoices']) > 0) {

            $invoice_totals = array_sum(array_column($data['invoices'], 'amount'));

            $invoices = Invoice::whereIn('id', array_column($data['invoices'], 'invoice_id'))->get();

            $payment->invoices()->saveMany($invoices);

            //todo optimize this into a single query
            foreach ($data['invoices'] as $paid_invoice) {
                $invoice = Invoice::whereId($paid_invoice['invoice_id'])->first();

                if ($invoice) {
                    $invoice = $invoice->service()
                                       ->markSent()
                                       ->applyPayment($payment, $paid_invoice['amount'])
                                       ->save();
                }
            }
        } else {
            //payment is made, but not to any invoice, therefore we are applying the payment to the clients paid_to_date only
            //01-07-2020 i think we were duplicating the paid to date here.
            //$payment->client->service()->updatePaidToDate($payment->amount)->save();
        }

        if (array_key_exists('credits', $data) && is_array($data['credits'])) {
            $credit_totals = array_sum(array_column($data['credits'], 'amount'));

            $credits = Credit::whereIn('id', $this->transformKeys(array_column($data['credits'], 'credit_id')))->get();
            $payment->credits()->saveMany($credits);

            //todo optimize into a single query
            foreach ($data['credits'] as $paid_credit) {
                $credit = Credit::withTrashed()->find($this->decodePrimaryKey($paid_credit['credit_id']));

                if ($credit) {
                    ApplyCreditPayment::dispatchNow($credit, $payment, $paid_credit['amount'], $credit->company);
                }
            }
        }

		if ( ! $is_existing_payment && ! $this->import_mode ) {

            if ($payment->client->getSetting('client_manual_payment_notification')) 
                $payment->service()->sendEmail();
			
            event( new PaymentWasCreated( $payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null) ) );
		}

        nlog("payment amount = {$payment->amount}");
        nlog("payment applied = {$payment->applied}");
        nlog("invoice totals = {$invoice_totals}");
        nlog("credit totals = {$credit_totals}");

        $payment->applied += ($invoice_totals - $credit_totals); //wont work because - check tests
        // $payment->applied += $invoice_totals; //wont work because - check tests

        $payment->save();

        return $payment->fresh();
    }

    /**
     * If the client is paying in a currency other than
     * the company currency, we need to set a record.
     * @param $data
     * @param $payment
     * @return
     */
    private function processExchangeRates($data, $payment)
    {
        $client = Client::find($data['client_id']);

        $client_currency = $client->getSetting('currency_id');
        $company_currency = $client->company->settings->currency_id;

        if ($company_currency != $client_currency) {

            $exchange_rate = new CurrencyApi();

            $payment->exchange_rate = $exchange_rate->exchangeRate($client_currency, $company_currency, Carbon::parse($payment->date));
            $payment->exchange_currency_id = $client_currency;
        }

        return $payment;
    }

    public function delete($payment)
    {
        //cannot double delete a payment
        if ($payment->is_deleted) {
            return;
        }

        $payment = $payment->service()->deletePayment();

        event(new PaymentWasDeleted($payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $payment;
        //return parent::delete($payment);
    }

    public function restore($payment)
    {
        //we cannot restore a deleted payment.
        if ($payment->is_deleted) {
            return;
        }

        return parent::restore($payment);
    }
}
