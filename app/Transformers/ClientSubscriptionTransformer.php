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

namespace App\Transformers;


use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\ClientSubscription;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;

class ClientSubscriptionTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'client',
        'recurring_invoice',
        'subscription',
    ];

    public function transform(ClientSubscription $client_subscription): array
    {
        return [
            'id' => $this->encodePrimaryKey($client_subscription->id),
            'subscription_id' => $this->encodePrimaryKey($client_subscription->subscription_id),
            'company_id' => $this->encodePrimaryKey($client_subscription->company_id),
            'recurring_invoice_id' => $this->encodePrimaryKey($client_subscription->recurring_invoice_id),
            'client_id' => $this->encodePrimaryKey($client_subscription->client_id),
            'trial_started' => (string)$client_subscription->trial_started ?: '',
            'trial_ends' => (string)$client_subscription->trial_ends ?: '',
            'is_deleted' => (bool)$client_subscription->is_deleted,
            'created_at' => (int)$client_subscription->created_at,
            'updated_at' => (int)$client_subscription->updated_at,
            'archived_at' => (int)$client_subscription->deleted_at,
        ];
    }

    public function includeClient(ClientSubscription $client_subscription): \League\Fractal\Resource\Item
    {
        $transformer = new ClientTransformer($this->serializer);

        return $this->includeItem($client_subscription->client, $transformer, Client::class);
    }

    public function includeRecurringInvoice(ClientSubscription $client_subscription): \League\Fractal\Resource\Item
    {
        $transformer = new RecurringInvoiceTransformer($this->serializer);

        return $this->includeItem($client_subscription->recurring_invoice, $transformer, RecurringInvoice::class);
    }

    public function includeSubscription(ClientSubscription $client_subscription): \League\Fractal\Resource\Item
    {
        $transformer = new BillingSubscriptionTransformer($this->serializer);

        return $this->includeItem($client_subscription->subscription, $transformer, BillingSubscription::class);
    }
}

