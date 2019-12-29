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


use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateClientPaidToDate
{
    use Dispatchable;

    protected $amount;

    protected $client;

    private $company;
    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Client $client, $amount, Company $company)
    {
        $this->amount = $amount;
        $this->client = $client;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() 
    {
        MultiDB::setDB($this->company->db);

        $this->client->paid_to_date += $this->amount;
        $this->client->save();

    }
}
