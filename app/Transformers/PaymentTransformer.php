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

namespace App\Transformers;

use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Transformers\PaymentableTransformer;
use App\Utils\Traits\MakesHash;

class PaymentTransformer extends EntityTransformer
{
    use MakesHash;

    protected $serializer;

    protected $defaultIncludes = [];

    protected $availableIncludes = [
        'client',
        'invoices',
        'paymentables'
    ];

    public function __construct($serializer = null)
    {
        $this->serializer = $serializer;
    }

    public function includeInvoices(Payment $payment)
    {
        $transformer = new InvoiceTransformer($this->serializer);

        return $this->includeCollection($payment->invoices, $transformer, Invoice::class);
    }

    public function includeClient(Payment $payment)
    {
        $transformer = new ClientTransformer($this->serializer);

        return $this->includeItem($payment->client, $transformer, Client::class);
    }

    public function includePaymentables(Payment $payment)
    {
        $transformer = new PaymentableTransformer($this->serializer);

        return $this->includeCollection($payment->paymentables, $transformer, Paymentable::class);
    }


    public function transform(Payment $payment)
    {
        return  [
            'id' => $this->encodePrimaryKey($payment->id),
            'user_id' => $this->encodePrimaryKey($payment->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($payment->assigned_user_id),
            'amount' => (float) $payment->amount,
            'refunded' => (float) $payment->refunded,
            'applied' => (float) $payment->applied,
            'transaction_reference' => $payment->transaction_reference ?: '',
            'date' => $payment->date ?: '',
            'is_manual' => (bool) $payment->is_manual,
            'updated_at' => (int)$payment->updated_at,
            'archived_at' => (int)$payment->deleted_at,
            'is_deleted' => (bool) $payment->is_deleted,
            'type_id' => (string) $payment->payment_type_id ?: '',
            'invitation_id' => (string) $payment->invitation_id ?: '',
            'number' => (string) $payment->number ?: '',
            'client_id' => (string) $this->encodePrimaryKey($payment->client_id),
            'client_contact_id' => (string) $this->encodePrimaryKey($payment->client_contact_id),
            'company_gateway_id' => (string) $this->encodePrimaryKey($payment->company_gateway_id),
            'status_id'=> (string) $payment->status_id,
            'type_id'=> (string) $payment->type_id,
            'project_id' => (string) $this->encodePrimaryKey($payment->project_id),
            'vendor_id' => (string) $this->encodePrimaryKey($payment->vendor_id),
/*
            'private_notes' => $payment->private_notes ?: '',
            'exchange_rate' => (float) $payment->exchange_rate,
            'exchange_currency_id' => (int) $payment->exchange_currency_id,
            'refunded' => (float) $payment->refunded,
            'payment_status_id' => (string) $payment->payment_status_id,
*/
        ];
    }
}
