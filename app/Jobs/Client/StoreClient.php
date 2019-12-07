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

namespace App\Jobs\Client;


use App\Models\Client;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoreClient
{
    use Dispatchable;

    protected $data;

    protected $client;

    /**
     * Create a new job instance.
     *
     * @param array $data
     * @param Client $client
     */

    public function __construct(array $data, Client $client)
    {
        $this->data = $data;

        $this->client = $client;
    }

    /**
     * Execute the job.
     *
     * @param ClientRepository $client_repo
     * @param ClientContactRepository $client_contact_repo
     * @return Client|null
     */
    public function handle(ClientRepository $client_repo, ClientContactRepository $client_contact_repo) : ?Client {

        $client =  $client_repo->save($this->data, $this->client);

        $contacts = $client_contact_repo->save($data['contacts']), $client);

        return $client;
    }
}
