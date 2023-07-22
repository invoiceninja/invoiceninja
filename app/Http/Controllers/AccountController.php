<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Models\Account;
use App\Libraries\MultiDB;
use App\Utils\TruthSource;
use App\Models\CompanyUser;
use Illuminate\Http\Response;
use App\Jobs\Account\CreateAccount;
use App\Transformers\AccountTransformer;
use App\Transformers\CompanyUserTransformer;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Http\Requests\Account\CreateAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;

class AccountController extends BaseController
{
    use DispatchesJobs;

    protected $entity_type = CompanyUser::class;

    protected $entity_transformer = CompanyUserTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        // return view('signup.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateAccountRequest $request
     * @return Response
     *
     */
    public function store(CreateAccountRequest $request)
    {
        $account = (new CreateAccount($request->all(), $request->getClientIp()))->handle();
        if (! ($account instanceof Account)) {
            return $account;
        }
        
        MultiDB::findAndSetDbByAccountKey($account->key);

        $cu = CompanyUser::where('user_id', $account->users()->first()->id);

        $company_user = $cu->first();

        $truth = app()->make(TruthSource::class);
        $truth->setCompanyUser($company_user);
        $truth->setUser($company_user->user);
        $truth->setCompany($company_user->company);
        $truth->setCompanyToken($company_user->tokens()->where('user_id', $company_user->user_id)->where('company_id', $company_user->company_id)->first());

        return $this->listResponse($cu);
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $fi = new \FilesystemIterator(public_path('react'), \FilesystemIterator::SKIP_DOTS);

        if (iterator_count($fi) < 30) {
            return response()->json(['message' => 'React App Not Installed, Please install the React app before attempting to switch.'], 400);
        }

        $account->fill($request->all());
        $account->save();

        $this->entity_type = Account::class;

        $this->entity_transformer = AccountTransformer::class;

        return $this->itemResponse($account);
    }
}
