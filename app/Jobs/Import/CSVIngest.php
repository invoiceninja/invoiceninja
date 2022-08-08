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

namespace App\Jobs\Import;

use App\Factory\ClientContactFactory;
use App\Factory\VendorContactFactory;
use App\Import\Providers\Csv;
use App\Import\Providers\Freshbooks;
use App\Import\Providers\Invoice2Go;
use App\Import\Providers\Invoicely;
use App\Import\Providers\Wave;
use App\Import\Providers\Zoho;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CSVIngest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Company $company;

    public string $hash;

    public string $import_type;

    public ?string $skip_header;

    public $column_map;

    public array $request;

    public $tries = 1;

    public function __construct(array $request, Company $company)
    {
        $this->company = $company;
        $this->request = $request;
        $this->hash = $request['hash'];
        $this->import_type = $request['import_type'];
        $this->skip_header = $request['skip_header'] ?? null;
        $this->column_map =
            ! empty($request['column_map']) ?
                array_combine(array_keys($request['column_map']), array_column($request['column_map'], 'mapping')) : null;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        set_time_limit(0);

        $engine = $this->bootEngine();

        foreach (['client', 'product', 'invoice', 'payment', 'vendor', 'expense'] as $entity) {
            $engine->import($entity);
        }

        $engine->finalizeImport();

        $this->checkContacts();
    }

    private function checkContacts()
    {
        $vendors = Vendor::withTrashed()->where('company_id', $this->company->id)->doesntHave('contacts')->get();

        foreach ($vendors as $vendor) {
            $new_contact = VendorContactFactory::create($vendor->company_id, $vendor->user_id);
            $new_contact->vendor_id = $vendor->id;
            $new_contact->contact_key = Str::random(40);
            $new_contact->is_primary = true;
            $new_contact->save();
        }

        $clients = Client::withTrashed()->where('company_id', $this->company->id)->doesntHave('contacts')->get();

        foreach ($clients as $client) {
            $new_contact = ClientContactFactory::create($client->company_id, $client->user_id);
            $new_contact->client_id = $client->id;
            $new_contact->contact_key = Str::random(40);
            $new_contact->is_primary = true;
            $new_contact->save();
        }
    }

    private function bootEngine()
    {
        switch ($this->import_type) {
            case 'csv':
                return new Csv($this->request, $this->company);
            case 'waveaccounting':
                return new Wave($this->request, $this->company);
            case 'invoicely':
                return new Invoicely($this->request, $this->company);
            case 'invoice2go':
                return new Invoice2Go($this->request, $this->company);
            case 'zoho':
                return new Zoho($this->request, $this->company);
            case 'freshbooks':
                return new Freshbooks($this->request, $this->company);
            default:
                nlog("could not return provider");
                break;
        }
    }
}
