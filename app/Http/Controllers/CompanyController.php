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

namespace App\Http\Controllers;

use App\DataMapper\DefaultSettings;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\DestroyCompanyRequest;
use App\Http\Requests\Company\EditCompanyRequest;
use App\Http\Requests\Company\ShowCompanyRequest;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Requests\SignupRequest;
use App\Jobs\Company\CreateCompany;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\RegisterNewAccount;
use App\Jobs\Util\UploadAvatar;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Repositories\CompanyRepository;
use App\Transformers\AccountTransformer;
use App\Transformers\CompanyTransformer;
use App\Transformers\CompanyUserTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Class CompanyController
 * @package App\Http\Controllers
 */
class CompanyController extends BaseController
{
    use DispatchesJobs;
    use MakesHash;

    protected $entity_type = Company::class;

    protected $entity_transformer = CompanyTransformer::class;

    protected $company_repo;

    public $forced_includes = [];

    /**
     * CompanyController constructor.
     */
    public function __construct(CompanyRepository $company_repo)
    {
    
        parent::__construct();

        $this->company_repo = $company_repo;

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $companies = Company::whereAccountId(auth()->user()->company()->account->id);

        return $this->listResponse($companies);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreateCompanyRequest $request)
    {

        $company = CompanyFactory::create(auth()->user()->company()->account->id);
        
        return $this->itemResponse($company);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\SignupRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCompanyRequest $request)
    {
        $this->forced_includes = ['company_user'];

        $company = CreateCompany::dispatchNow($request->all(), auth()->user()->company()->account);

        if($request->file('logo')) 
        {

            $path = UploadAvatar::dispatchNow($request->file('logo'), $company->company_key);

            if($path){

                $settings = $company->settings;
                $settings->logo_url = config('ninja.site_url').$path;
                $company->settings = $settings;
                $company->save();
            }
            
        }

        auth()->user()->companies()->attach($company->id, [
            'account_id' => $company->account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => json_encode([]),
            'settings' => json_encode(DefaultSettings::userSettings()),
        ]);

        /*
         * Required dependencies
         */
        auth()->user()->setCompany($company);

        /*
         * Create token
         */
        $company_token = CreateCompanyToken::dispatchNow($company, auth()->user());

        //todo Need to discuss this with Hillel which is the best representation to return
        //when a company is created. Do we send the entire account? Do we only send back the created CompanyUser?
        $this->entity_transformer = CompanyUserTransformer::class;
        $this->entity_type = CompanyUser::class;
        
        //return $this->itemResponse($company);
        $ct = CompanyUser::whereUserId(auth()->user()->id);
        
        return $this->listResponse($ct);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ShowCompanyRequest $request, Company $company)
    {

        return $this->itemResponse($company);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(EditCompanyRequest $request, Company $company)
    {

        return $this->itemResponse($company);       

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $company = $this->company_repo->save($request->all(), $company);

        if($request->file('logo')) 
        {

            $path = UploadAvatar::dispatchNow($request->file('logo'), $company->company_key);

            if($path){

                $settings = $company->settings;
                $settings->logo_url = config('ninja.site_url').$path;
                $company->settings = $settings;
                $company->save();
            }
            
        }

        return $this->itemResponse($company);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyCompanyRequest $request, Company $company)
    {

        $company->delete();

        return response()->json([], 200);
    }
}
