<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\Client;
use App\Repositories\ClientContactRepository;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;

/**
 * ClientRepository
 */
class ClientRepository extends BaseRepository
{
    use GeneratesCounter;
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
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {

        return Client::class;

    }

	/**
     * Saves the client and its contacts
     *
     * @param      array                           $data    The data
     * @param      \App\Models\Client              $client  The client
     *
     * @return     Client|\App\Models\Client|null  Client Object
     */
    public function save(array $data, Client $client) : ?Client
	{
        $client->fill($data);

        $client->save();

        if($client->id_number == "" || !$client->id_number)
            $client->id_number = $this->getNextClientNumber($client); //todo write tests for this and make sure that custom client numbers also works as expected from here

        $client->save();

        if(isset($data['contacts']))
            $contacts = $this->contact_repo->save($data['contacts'], $client);


        if(empty($data['name']))
            $data['name'] = $client->present()->name();


        return $client;
        
	}


}