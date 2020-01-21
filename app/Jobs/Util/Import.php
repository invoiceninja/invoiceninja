<?php

namespace App\Jobs\Util;

use App\Exceptions\ResourceNotAvailableForMigration;
use App\Factory\TaxRateFactory;
use App\Factory\UserFactory;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\ValidationRules\ValidUserForCompany;
use App\Jobs\Company\CreateCompanyToken;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\TaxRate;
use App\Models\User;
use App\Repositories\CompanyRepository;
use App\Repositories\UserRepository;
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
        'company', 'tax_rates', 'users',
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
     * Local state manager for ids.
     *
     * @var array
     */
    private $ids;

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

            $modified = $resource;
            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->user->id;

            $tax_rate = TaxRateFactory::create($this->company->id, $this->user->id);
            $tax_rate->fill($resource);
            $tax_rate->save();
        }
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    private function processUsers(array $data): void
    {
        User::unguard();

        $rules = [
            '*.first_name' => ['required', 'string', 'max:100'],
            '*.last_name' => ['required', 'string', 'max:100'],
            '*.email' => ['distinct'],
        ];

        if (config('ninja.db.multi_db_enabled')) {
            array_push($rules['*.email'], new ValidUserForCompany());
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new \Exception($validator->errors());
        }

        $user_repository = new UserRepository();

        foreach ($data as $resource) {

            $modified = $resource;
            unset($modified['id']);

            $user = $user_repository->save($modified, $this->fetchUser($resource['email']));

            $user_agent = array_key_exists('token_name', $resource) ?: request()->server('HTTP_USER_AGENT');

            CreateCompanyToken::dispatchNow($this->company, $user, $user_agent);

            $key = "users_{$resource['id']}";

            $this->ids['users'][$key] = [
                'old' => $resource['id'],
                'new' => $user->id,
            ];
        }
    }

    /**
     * |--------------------------------------------------------------------------
     * | Additional migration methods.
     * |--------------------------------------------------------------------------
     * |
     * | These methods aren't initialized automatically, so they don't depend on
     * | the migration data.
     */

    /**
     * Cloned from App\Http\Requests\User\StoreUserRequest.
     *
     * @param string $data
     * @return User
     */
    public function fetchUser(string $data): User
    {
        $user = MultiDB::hasUser(['email' => $data]);

        if (!$user) {
            $user = UserFactory::create();
        }

        return $user;
    }
}
