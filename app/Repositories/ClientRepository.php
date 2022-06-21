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

namespace App\Repositories;

use App\Factory\ClientFactory;
use App\Models\Client;
use App\Models\Company;
use App\Utils\Traits\ClientGroupSettingsSaver;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Database\QueryException;

/**
 * ClientRepository.
 */
class ClientRepository extends BaseRepository
{
    use GeneratesCounter;
    use SavesDocuments;

    private bool $completed = true;

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
        if (array_key_exists('documents', $data) && count($data['documents']) >= 1) {
            $this->saveDocuments($data['documents'], $client);

            return $client;
        }

        $client->fill($data);

        if (array_key_exists('settings', $data)) {
            $client->saveSettings($data['settings'], $client);
        }

        if (! $client->country_id) {
            $company = Company::find($client->company_id);
            $client->country_id = $company->settings->country_id;
        }

        $client->save();

        if (! isset($client->number) || empty($client->number) || strlen($client->number) == 0) {
            // $client->number = $this->getNextClientNumber($client);
            // $client->save();

            $x = 1;

            do {
                try {
                    $client->number = $this->getNextClientNumber($client);
                    $client->saveQuietly();

                    $this->completed = false;
                } catch (QueryException $e) {
                    $x++;

                    if ($x > 10) {
                        $this->completed = false;
                    }
                }
            } while ($this->completed);
        }

        if (empty($data['name'])) {
            $data['name'] = $client->present()->name();
        }

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

    public function purge($client)
    {
        $client->contacts()->forceDelete();
        $client->tasks()->forceDelete();
        $client->invoices()->forceDelete();
        $client->ledger()->forceDelete();
        $client->gateway_tokens()->forceDelete();
        $client->projects()->forceDelete();
        $client->credits()->forceDelete();
        $client->quotes()->forceDelete();
        $client->activities()->forceDelete();
        $client->recurring_invoices()->forceDelete();
        $client->expenses()->forceDelete();
        $client->recurring_expenses()->forceDelete();
        $client->system_logs()->forceDelete();
        $client->documents()->forceDelete();
        $client->payments()->forceDelete();
        $client->forceDelete();
    }
}
