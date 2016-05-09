<?php namespace App\Http\Controllers;

use Auth;
use Session;
use DB;
use Validator;
use Input;
use View;
use Redirect;
use Datatable;
use URL;

use App\Models\AccountToken;
use App\Services\TokenService;
use App\Ninja\Repositories\AccountRepository;

class TokenController extends BaseController
{
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        //parent::__construct();

        $this->tokenService = $tokenService;
    }

    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_API_TOKENS);
    }

    public function getDatatable()
    {
        return $this->tokenService->getDatatable(Auth::user()->id);
    }

    public function edit($publicId)
    {
        $token = AccountToken::where('account_id', '=', Auth::user()->account_id)
                        ->where('public_id', '=', $publicId)->firstOrFail();

        $data = [
            'token' => $token,
            'method' => 'PUT',
            'url' => 'tokens/'.$publicId,
            'title' => trans('texts.edit_token'),
        ];

        return View::make('accounts.token', $data);
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
          'token' => null,
          'method' => 'POST',
          'url' => 'tokens',
          'title' => trans('texts.add_token'),
        ];

        return View::make('accounts.token', $data);
    }

    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');
        $count = $this->tokenService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_token'));

        return Redirect::to('settings/' . ACCOUNT_API_TOKENS);
    }

    /**
     * Stores new account
     *
     */
    public function save($tokenPublicId = false)
    {
        if (Auth::user()->account->hasFeature(FEATURE_API)) {
            $rules = [
                'name' => 'required',
            ];

            if ($tokenPublicId) {
                $token = AccountToken::where('account_id', '=', Auth::user()->account_id)
                            ->where('public_id', '=', $tokenPublicId)->firstOrFail();
            }

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to($tokenPublicId ? 'tokens/edit' : 'tokens/create')->withInput()->withErrors($validator);
            }

            if ($tokenPublicId) {
                $token->name = trim(Input::get('name'));
            } else {
                $token = AccountToken::createNew();
                $token->name = trim(Input::get('name'));
                $token->token = str_random(RANDOM_KEY_LENGTH);
            }

            $token->save();

            if ($tokenPublicId) {
                $message = trans('texts.updated_token');
            } else {
                $message = trans('texts.created_token');
            }

            Session::flash('message', $message);
        }

        return Redirect::to('settings/' . ACCOUNT_API_TOKENS);
    }

}
