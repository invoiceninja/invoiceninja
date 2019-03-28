<?php

namespace App\Transformers;

use App\Models\Client;
use App\Models\ClientContact;

/**
 * @SWG\Definition(definition="Client", @SWG\Xml(name="Client"))
 */
class ClientTransformer extends EntityTransformer
{
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     */
    protected $defaultIncludes = [
        'contacts',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
    ];


    /**
     * @param Client $client
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeContacts(Client $client)
    {
        $transformer = new ClientContactTransformer($this->serializer);

        return $this->includeCollection($client->contacts, $transformer, ClientContact::class);
    }


    /**
     * @param Client $client
     *
     * @return array
     */
    public function transform(Client $client)
    {
        return [
            'id' => (int) $client->id,
            'name' => $client->name ?: '',
        ];
    }
}
