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

use App\Models\Company;
use App\Models\Payment;
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
            'date' => data_get($qb_data, 'TxnDate', now()->format('Y-m-d')),  
            'amount' => floatval(data_get($qb_data, 'TotalAmt', 0)), 
            'applied' => data_get($qb_data, 'TotalAmt', 0) - data_get($qb_data, 'UnappliedAmt', 0), 
            'number' => data_get($qb_data, 'DocNumber', null),
            'private_notes' => data_get($qb_data, 'PrivateNote', null),
            'currency_id' => (string) $this->resolveCurrency(data_get($qb_data, 'CurrencyRef.value')),
            'client_id' => $this->getClientId(data_get($qb_data, 'CustomerRef.value', null)),    
        ];
    }
 
    public function buildPayment($qb_data): ?Payment
    {
        $ninja_payment_data = $this->transform($qb_data);

        if($ninja_payment_data['client_id'])
        {
            $payment = PaymentFactory::create($this->company->id, $this->company->owner()->id,$ninja_payment_data['client_id']);
            $payment->amount = $ninja_payment_data['amount'];
            $payment->applied = $ninja_payment_data['applied'];
            $payment->status_id = 4;
            $payment->fill($ninja_payment_data);
            
            $payment->client->service()->updatePaidToDate($payment->amount);

            return $payment;
        }

        return null;
    }

    public function getLine($data, $field = null)
    {
        $invoices = [];
        $invoice = $this->getString($data, 'Line.LinkedTxn.TxnType');
        if(is_null($invoice) || $invoice !== 'Invoice') {
            return $invoices;
        }
        if(is_null(($invoice_id = $this->getInvoiceId($this->getString($data, 'Line.LinkedTxn.TxnId.value'))))) {
            return $invoices;
        }

        return [[
            'amount' => (float) $this->getString($data, 'Line.Amount'),
            'invoice_id' => $invoice_id
        ]];
    }

}
