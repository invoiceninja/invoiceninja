<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Client;
use League\Fractal;
use App\Ninja\Transformers\InvoiceTransformer;

/**
 * @SWG\Definition(definition="Payment", required={"invoice_id"}, @SWG\Xml(name="Payment"))
 */

class PaymentTransformer extends EntityTransformer
{
    /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="amount", type="float", example=10, readOnly=true)
    * @SWG\Property(property="invoice_id", type="integer", example=1)
    */
    protected $defaultIncludes = [];


    public function __construct(Account $account)
    {
        parent::__construct($account);

    }

    public function includeInvoice(Payment $payment)
    {
        $transformer = new InvoiceTransformer($this->account, $this->serializer);
        return $this->includeItem($payment->invoice, $transformer, 'invoice');
    }

    public function includeClient(Payment $payment)
    {
        $transformer = new ClientTransformer($this->account, $this->serializer);
        return $this->includeItem($payment->client, $transformer, 'client');
    }

    public function transform(Payment $payment)
    {
        return [
            'id' => (int) $payment->public_id,
            'amount' => (float) $payment->amount,
            'account_key' => $this->account->account_key,
            'user_id' => (int) $payment->user->public_id + 1,
            'transaction_reference' => $payment->transaction_reference,
            'payment_date' => $payment->payment_date,
            'updated_at' => $this->getTimestamp($payment->updated_at),
            'archived_at' => $this->getTimestamp($payment->deleted_at),
            'is_deleted' => (bool) $payment->is_deleted,
            'payment_type_id' => (int) $payment->payment_type_id,
        ];
    }
}