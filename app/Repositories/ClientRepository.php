<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Factory\ClientFactory;
use App\Models\Client;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\SavesDocuments;

/**
 * ClientRepository.
 */
class ClientRepository extends BaseRepository
{
    use GeneratesCounter;
    use SavesDocuments;

    /**
     * @var ClientContactRepository
     */
    protected $contact_repo;

    /**
     * ClientController constructor.
     * @param ClientContactRepository $contact_repo
     */
    public function __construct(ClientContactRepository $contact_repo)
    {
        $this->contact_repo = $contact_repo;
    }
    
    /**
     * Saves the client and its contacts.
     *
     * @param array $data The data
     * @param Client $client The client
     *
     * @return     Client|Client|null  Client Object
     *
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     * @todo       Write tests to make sure that custom client numbers work as expected.
     */
    public function save(array $data, Client $client) : ?Client
    {

        /* When uploading documents, only the document array is sent, so we must return early*/
        if (array_key_exists('documents', $data) && count($data['documents']) >=1) {
            $this->saveDocuments($data['documents'], $client);
            return $client;
        }

        $client->fill($data);

        if (!isset($client->id_number) || empty($client->id_number)) {
            $client->id_number = $this->getNextClientNumber($client);
        }

        if (empty($data['name'])) {
            $data['name'] = $client->present()->name();
        }
        
        $client->save();

        $this->contact_repo->save($data, $client);

        return $client;
    }

    /**
     * Store clients in bulk.
     *
     * @param array $client
     * @return Client|null
     */
    public function create($client): ?Client
    {
        return $this->save(
            $client,
            ClientFactory::create(auth()->user()->company()->id, auth()->user()->id)
        );
    }
}
