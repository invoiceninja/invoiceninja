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

use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\CompanyLedger;
use App\Models\Document;
use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;
use League\Fractal\Resource\Collection;
use stdClass;

/**
 * class ClientTransformer.
 */
class ClientTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
        'contacts',
        'documents',
        'gateway_tokens',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'activities',
        'ledger',
        'system_logs',
    ];

    /**
     * @param Client $client
     *
     * @return Collection
     */
    public function includeActivities(Client $client)
    {
        $transformer = new ActivityTransformer($this->serializer);

        return $this->includeCollection($client->activities, $transformer, Activity::class);
    }

    public function includeDocuments(Client $client)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($client->documents, $transformer, Document::class);
    }

    /**
     * @param Client $client
     *
     * @return Collection
     */
    public function includeContacts(Client $client)
    {
        $transformer = new ClientContactTransformer($this->serializer);

        return $this->includeCollection($client->contacts, $transformer, ClientContact::class);
    }

    public function includeGatewayTokens(Client $client)
    {
        $transformer = new ClientGatewayTokenTransformer($this->serializer);

        return $this->includeCollection($client->gateway_tokens, $transformer, ClientGatewayToken::class);
    }

    public function includeLedger(Client $client)
    {
        $transformer = new CompanyLedgerTransformer($this->serializer);

        return $this->includeCollection($client->ledger, $transformer, CompanyLedger::class);
    }

    public function includeSystemLogs(Client $client)
    {
        $transformer = new SystemLogTransformer($this->serializer);

        return $this->includeCollection($client->system_logs, $transformer, SystemLog::class);
    }

    /**
     * @param Client $client
     *
     * @return array
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     */
    public function transform(Client $client)
    {
        return [
            'id' => $this->encodePrimaryKey($client->id),
            'user_id' => $this->encodePrimaryKey($client->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($client->assigned_user_id),
            'name' => $client->name ?: '',
            'website' => $client->website ?: '',
            'private_notes' => $client->private_notes ?: '',
            'balance' => (float) $client->balance,
            'group_settings_id' => isset($client->group_settings_id) ? (string) $this->encodePrimaryKey($client->group_settings_id) : '',
            'paid_to_date' => (float) $client->paid_to_date,
            'credit_balance' => (float) $client->credit_balance,
            'last_login' => (int) $client->last_login,
            'size_id' => (string) $client->size_id,
            'public_notes' => $client->public_notes ?: '',
            'client_hash' => (string) $client->client_hash,
            'address1' => $client->address1 ?: '',
            'address2' => $client->address2 ?: '',
            'phone' => $client->phone ?: '',
            'city' => $client->city ?: '',
            'state' => $client->state ?: '',
            'postal_code' => $client->postal_code ?: '',
            'country_id' => (string) $client->country_id ?: '',
            'industry_id' => (string) $client->industry_id ?: '',
            'custom_value1' => $client->custom_value1 ?: '',
            'custom_value2' => $client->custom_value2 ?: '',
            'custom_value3' => $client->custom_value3 ?: '',
            'custom_value4' => $client->custom_value4 ?: '',
            'shipping_address1' => $client->shipping_address1 ?: '',
            'shipping_address2' => $client->shipping_address2 ?: '',
            'shipping_city' => $client->shipping_city ?: '',
            'shipping_state' => $client->shipping_state ?: '',
            'shipping_postal_code' => $client->shipping_postal_code ?: '',
            'shipping_country_id' => (string) $client->shipping_country_id ?: '',
            'settings' => $client->settings ?: new stdClass,
            'is_deleted' => (bool) $client->is_deleted,
            'vat_number' => $client->vat_number ?: '',
            'id_number' => $client->id_number ?: '',
            'updated_at' => (int) $client->updated_at,
            'archived_at' => (int) $client->deleted_at,
            'created_at' => (int) $client->created_at,
            'display_name' => $client->present()->name(),
            'number' => (string) $client->number ?: '',
        ];
    }
}
