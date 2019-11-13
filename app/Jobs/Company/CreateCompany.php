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

    public function __construct(array $request, $account)
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
        $settings = CompanySettings::defaults();

        $default_company_name = isset($this->request['first_name']) ? $this->request['first_name'] : '' . ' ' . isset($this->request['last_name']) ? $this->request['last_name'] : '';

        $settings->name = isset($this->request['name']) ? $this->request['name'] :  $default_company_name;

        $company = new Company();
        $company->account_id = $this->account->id;
        $company->company_key = $this->createHash();
        $company->ip = request()->ip();
        $company->settings = $settings;
        $company->db = config('database.default');
        $company->domain = isset($this->request['domain']) ? $this->request['domain'] : config('ninja.site_url');
        $company->save();


        return $company;
    }
}
