<?php

namespace App\Http\Controllers\Migration\Account;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Migration\Account\SelfLoginRequest;
use App\Services\Migration\Account\LoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoginController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return view('migration.account');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        if (session('version') == 'hosted') {
            // ..
        }

        if (session('version') == 'self_hosted') {

            $validator = Validator::make($request->all(), (new SelfLoginRequest())->rules());

            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

            $loginService = new LoginService($request->all());
            $loginService->login();

            if ($loginService->getSuccessful()) {
                return redirect('/migration/company')->with('message', 'Login was successful.');
            }

            return back()->with('message', $loginService->response->message);

        }
    }
}
