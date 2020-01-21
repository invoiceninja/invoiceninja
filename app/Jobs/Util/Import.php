<?php

namespace App\Jobs\Util;

use App\Exceptions\ResourceNotAvailableForMigration;
use App\Factory\TaxRateFactory;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\ValidationRules\ValidSettingsRule;
use App\Models\Company;
use App\Models\TaxRate;
use App\Models\User;
use App\Repositories\CompanyRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Import implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array
     */
    private $data;

    /**
     * @var Company
     */
    private $company;

    /**
     * @var array
     */
    private $available_imports = [
        'company', 'tax_rates',
    ];

    /**
     * @var User
     */
    private $user;

    /**
     * Custom list of resources to be imported.
     *
     * @var array
     */
    private $resources;

    /**
     * Create a new job instance.
     *
     * @param array $data
     * @param Company $company
     * @param User $user
     * @param array $resources
     */
    public function __construct(array $data, Company $company, User $user, array $resources = [])
    {
        $this->data = $data;
        $this->company = $company;
        $this->user = $user;
        $this->resources = $resources;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        foreach ($this->data as $key => $resource) {

            if (!in_array($key, $this->available_imports)) {
                throw new ResourceNotAvailableForMigration($key);
            }

            $method = sprintf("process%s", Str::ucfirst(Str::camel($key)));

            $this->{$method}($resource);
        }
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    private function processCompany(array $data): void
    {
        Company::unguard();

        $rules = (new UpdateCompanyRequest())->rules();

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new \Exception($validator->errors());
        }

        $company_repository = new CompanyRepository();
        $company_repository->save($data, $this->company);
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    private function processTaxRates(array $data): void
    {
        TaxRate::unguard();

        $rules = [
            '*.name' => 'required|distinct|unique:tax_rates,name,null,null,company_id,' . $this->company->id,
            '*.rate' => 'required|numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new \Exception($validator->errors());
        }

        foreach ($data as $resource) {
            $tax_rate = TaxRateFactory::create($this->company->id, $this->user->id);
            $tax_rate->fill($resource);
            $tax_rate->save();
        }
    }
}
