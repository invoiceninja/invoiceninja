<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBankAccountRequest;
use App\Models\Account;
use App\Models\BankAccount;
use App\Ninja\Repositories\BankAccountRepository;
use App\Services\BankAccountService;
use Auth;
use Cache;
use Crypt;
use File;
use Illuminate\Http\Request;
use Redirect;
use Session;
use Utils;
use View;

class BankAccountController extends BaseController
{
    protected $bankAccountService;
    protected $bankAccountRepo;

    public function __construct(BankAccountService $bankAccountService, BankAccountRepository $bankAccountRepo)
    {
        //parent::__construct();

        $this->bankAccountService = $bankAccountService;
        $this->bankAccountRepo = $bankAccountRepo;
    }

    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_BANKS);
    }

    public function getDatatable()
    {
        return $this->bankAccountService->getDatatable(Auth::user()->account_id);
    }

    public function edit($publicId)
    {
        $bankAccount = BankAccount::scope($publicId)->firstOrFail();

        $data = [
            'title' => trans('texts.edit_bank_account'),
            'banks' => Cache::get('banks'),
            'bankAccount' => $bankAccount,
        ];

        return View::make('accounts.bank_account', $data);
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    /**
     * Displays the form for account creation.
     */
    public function create()
    {
        $data = [
            'banks' => Cache::get('banks'),
            'bankAccount' => null,
        ];

        return View::make('accounts.bank_account', $data);
    }

    public function bulk()
    {
        $action = \Request::input('bulk_action');
        $ids = \Request::input('bulk_public_id');
        $count = $this->bankAccountService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_bank_account'));

        return Redirect::to('settings/' . ACCOUNT_BANKS);
    }

    public function validateAccount()
    {
        $publicId = \Request::input('public_id');
        $username = trim(\Request::input('bank_username'));
        $password = trim(\Request::input('bank_password'));

        if ($publicId) {
            $bankAccount = BankAccount::scope($publicId)->firstOrFail();
            if ($username != $bankAccount->username) {
                $bankAccount->setUsername($username);
                $bankAccount->save();
            } else {
                $username = Crypt::decrypt($username);
            }
            $bankId = $bankAccount->bank_id;
        } else {
            $bankAccount = new BankAccount;
            $bankAccount->bank_id = \Request::input('bank_id');
        }

        $bankAccount->app_version = \Request::input('app_version');
        $bankAccount->ofx_version = \Request::input('ofx_version');

        if ($publicId) {
            $bankAccount->save();
        }

        return json_encode($this->bankAccountService->loadBankAccounts($bankAccount, $username, $password, $publicId));
    }

    public function store(CreateBankAccountRequest $request)
    {
        $bankAccount = $this->bankAccountRepo->save(Request::all());

        $bankId = \Request::input('bank_id');
        $username = trim(\Request::input('bank_username'));
        $password = trim(\Request::input('bank_password'));

        return json_encode($this->bankAccountService->loadBankAccounts($bankAccount, $username, $password, true));
    }

    public function importExpenses($bankId)
    {
        return $this->bankAccountService->importExpenses($bankId, Request::all());
    }

    public function showImportOFX()
    {
        return view('accounts.import_ofx');
    }

    public function doImportOFX(Request $request)
    {
        $file = File::get($request->file('ofx_file'));

        try {
            $data = $this->bankAccountService->parseOFX($file);
        } catch (\Exception $e) {
            Session::now('error', trans('texts.ofx_parse_failed'));
            Utils::logError($e);

            return view('accounts.import_ofx');
        }

        $data = [
            'banks' => null,
            'bankAccount' => null,
            'transactions' => json_encode([$data]),
        ];

        return View::make('accounts.bank_account', $data);
    }
}
