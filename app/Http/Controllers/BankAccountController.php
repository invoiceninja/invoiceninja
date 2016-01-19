<?php namespace App\Http\Controllers;

use Crypt;
use Cache;
use Auth;
use Datatable;
use DB;
use Input;
use Redirect;
use Session;
use View;
use Validator;
use stdClass;
use URL;
use Utils;
use App\Models\Gateway;
use App\Models\Account;
use App\Models\BankAccount;
use App\Ninja\Repositories\AccountRepository;
use App\Services\BankAccountService;

class BankAccountController extends BaseController
{
    protected $bankAccountService;

    public function __construct(BankAccountService $bankAccountService)
    {
        parent::__construct();

        $this->bankAccountService = $bankAccountService;
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
        $bankAccount->username = str_repeat('*', 16);

        $data = [
            'url' => 'bank_accounts/' . $publicId,
            'method' => 'PUT',
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

    public function store()
    {
        return $this->save();
    }

    /**
     * Displays the form for account creation
     *
     */
    public function create()
    {
        $data = [
            'url' => 'bank_accounts',
            'method' => 'POST',
            'title' => trans('texts.add_bank_account'),
            'banks' => Cache::get('banks'),
            'bankAccount' => null,
        ];

        return View::make('accounts.bank_account', $data);
    }

    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');
        $count = $this->bankAccountService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_bank_account'));

        return Redirect::to('settings/' . ACCOUNT_BANKS);
    }

    /**
     * Stores new account
     *
     */
    public function save($bankAccountPublicId = false)
    {
        $account = Auth::user()->account;
        $bankId = Input::get('bank_id');
        $username = Input::get('bank_username');

        $rules = [
            'bank_id' => $bankAccountPublicId ? '' : 'required',
            'bank_username' => 'required',
        ];

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('bank_accounts/create')
                ->withErrors($validator)
                ->withInput();
        } else {
            if ($bankAccountPublicId) {
                $bankAccount = BankAccount::scope($bankAccountPublicId)->firstOrFail();
            } else {
                $bankAccount = BankAccount::createNew();
                $bankAccount->bank_id = $bankId;
            }

            if ($username != str_repeat('*', strlen($username))) {
                $bankAccount->username = Crypt::encrypt(trim($username));
            }

            if ($bankAccountPublicId) {
                $bankAccount->save();
                $message = trans('texts.updated_bank_account');
            } else {
                $account->bank_accounts()->save($bankAccount);
                $message = trans('texts.created_bank_account');
            }

            Session::flash('message', $message);
            return Redirect::to("bank_accounts/{$bankAccount->public_id}/edit");
        }
    }

    public function test()
    {
        $bankId = Input::get('bank_id');
        $username = Input::get('bank_username');
        $password = Input::get('bank_password');

        return json_encode($this->bankAccountService->loadBankAccounts($bankId, $username, $password, false));
    }

}
