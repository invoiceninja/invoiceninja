<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use stdClass;
use App\Models\Client;
use App\Models\Activity;
use App\Models\Document;
use App\Models\Location;
use App\Models\SystemLog;
use App\Models\GroupSetting;
use App\Models\ClientContact;
use App\Models\CompanyLedger;
use App\Utils\Traits\MakesHash;
use App\Models\ClientGatewayToken;

/**
 * class ClientProfileTransformer.
 */
class ClientProfileTransformer extends ClientTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [
        'contacts',
        'locations',
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'group_settings',
    ];

    /**
     * @param Client $client
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeContacts(Client $client)
    {
        $transformer = new ClientContactProfileTransformer($this->serializer, $this->user);

        return $this->includeCollection($client->contacts, $transformer, ClientContact::class);
    }

    /**
     * @param Client $client
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeLocations(Client $client)
    {
        $transformer = new LocationTransformer($this->serializer, $this->user);

        return $this->includeCollection($client->locations, $transformer, Location::class);
    }

    public function includeGroupSettings(Client $client)
    {
        if (!$client->group_settings) {
            return null;
        }

        $transformer = new GroupSettingTransformer($this->serializer, $this->user);

        return $this->includeItem($client->group_settings, $transformer, GroupSetting::class);
    }

    /**
     * @param Client $client
     *
     * @return array
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     */
    public function transform(Client $client)
    {
        return array_merge(parent::transform($client), [
            'private_notes' => '',
            'balance' => (float) 0,
            'paid_to_date' => (float) 0,
            'payment_balance' => (float) 0,
            'credit_balance' => (float) 0,
            'client_hash' => '',
            'last_login' => 0,
            'size_id' => '',
            'public_notes' => '',
            'industry_id' => '',
            'custom_value1' => '',
            'custom_value2' => '',
            'custom_value3' => '',
            'custom_value4' => '',
            'routing_id' => '',
            'tax_info' => new \stdClass(),
            'e_invoice' =>new \stdClass(),
            'tags' => $this->transformTags($client),
        ]);
    }
}
