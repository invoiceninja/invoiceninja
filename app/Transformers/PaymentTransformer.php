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

namespace App\Transformers;

use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Utils\Traits\MakesHash;

class PaymentTransformer extends EntityTransformer
{
    use MakesHash;

    protected $serializer;

    protected $defaultIncludes = [
        'paymentables',
        'documents',
    ];

    protected $availableIncludes = [
        'client',
        'invoices',
    ];

    public function __construct($serializer = null)
    {
        parent::__construct();

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

    public function includeDocuments(Payment $payment)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($payment->documents, $transformer, Document::class);
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
            'created_at' => (int) $payment->created_at,
            'updated_at' => (int) $payment->updated_at,
            'archived_at' => (int) $payment->deleted_at,
            'is_deleted' => (bool) $payment->is_deleted,
            'type_id' => (string) $payment->type_id ?: '',
            'invitation_id' => (string) $payment->invitation_id ?: '',
            'private_notes' => (string) $payment->private_notes ?: '',
            'number' => (string) $payment->number ?: '',
            'custom_value1' => (string) $payment->custom_value1 ?: '',
            'custom_value2' => (string) $payment->custom_value2 ?: '',
            'custom_value3' => (string) $payment->custom_value3 ?: '',
            'custom_value4' => (string) $payment->custom_value4 ?: '',
            'client_id' => (string) $this->encodePrimaryKey($payment->client_id),
            'client_contact_id' => (string) $this->encodePrimaryKey($payment->client_contact_id),
            'company_gateway_id' => (string) $this->encodePrimaryKey($payment->company_gateway_id),
            'status_id'=> (string) $payment->status_id,
            'project_id' => (string) $this->encodePrimaryKey($payment->project_id),
            'vendor_id' => (string) $this->encodePrimaryKey($payment->vendor_id),
            'currency_id' => (string) $payment->currency_id ?: '',
            'exchange_rate' => (float) $payment->exchange_rate ?: 1,
            'exchange_currency_id' => (string) $payment->exchange_currency_id ?: '',
        ];
    }
}
