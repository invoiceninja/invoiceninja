<?php

namespace App\Jobs\Company;

use App\DataMapper\CompanySettings;
use App\Events\UserSignedUp;
use App\Models\Company;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;

class CreateCompany
{
    use MakesHash;
    use Dispatchable;

    protected $request;

    protected $account;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(array $request, $account = false)
    {
        $this->request = $request;
        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : ?Company
    {

        $company = new Company();
        $company->name = $this->request['first_name'] . ' ' . $this->request['last_name'];
        $company->account_id = $this->account->id;
        $company->company_key = $this->createHash();
        $company->ip = request()->ip();
        $company->settings = CompanySettings::defaults();
        $company->db = config('database.default');
        $company->save();


        return $company;
    }
}
