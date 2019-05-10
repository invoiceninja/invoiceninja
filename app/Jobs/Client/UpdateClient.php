<?php

namespace App\Jobs\Client;


use App\Models\Client;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpdateClient
{
    use Dispatchable;

    protected $data;

    protected $client;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(array $data, Client $client)
    {
        $this->data = $data;
        $this->client = $client;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ClientRepository $client_repo, ClientContactRepository $client_contact_repo) :?Client
    {
        $client = $client_repo->save($this->data, $this->client);
        
        $contacts = $client_contact_repo->save($data['contacts']), $client);
        
        return $client->fresh();
    }
}
