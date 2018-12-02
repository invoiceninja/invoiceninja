<?php

namespace App\Jobs\Client;


use App\Models\Client;
use App\Repositories\ClientRepository;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoreClient
{
    use Dispatchable;

    protected $request;

    protected $client;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Request $request, Client $client)
    {
        $this->request = $request;
        $this->client = $client;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ClientRepository $clientRepo) : ?Client
    {
        return $clientRepo->save($this->request, $this->client);
    }
}
