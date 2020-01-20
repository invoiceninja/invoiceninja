<?php

namespace App\Jobs\Util;

use App\Exceptions\ResourceNotAvailableForMigration;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\ValidationRules\ValidSettingsRule;
use App\Models\Company;
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
        'company',
    ];
    /**
     * @var User
     */
    private $user;

    /**
     * Create a new job instance.
     *
     * @param array $data
     * @param Company $company
     * @param User $user
     */
    public function __construct(array $data, Company $company, User $user)
    {
        $this->data = $data;
        $this->company = $company;
        $this->user = $user;
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
        $rules = (new UpdateCompanyRequest())->rules();

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new \Exception($validator->errors());
        }

        $company_repository = new CompanyRepository();
        $company = $company_repository->save($data, $this->company);
    }
}
