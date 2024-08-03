<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Client;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Services\Tax\TaxService;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class CheckVat implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesHash;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param Client $client
     * @param Company $company
     */
    public function __construct(public Client $client, protected Company $company)
    {
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $tax_service = new TaxService($this->client);
        $tax_service->validateVat();

    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->client->client_hash)];
    }

}
