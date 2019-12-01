<?php

namespace App\Http\Controllers\Migration\Account;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Migration\Account\SelfRegisterRequest;
use App\Services\Migration\Account\RegisterService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class RegisterController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return view('migration.register');
    }

    public function register(Request $request)
    {
        if (session('version') == 'self_hosted') {

            $validator = Validator::make($request->all(), (new SelfRegisterRequest())->rules());

            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

            $registerService = new RegisterService($request->all());
            $registerService->register();

            if ($registerService->successful) {
                return back()->with('success', 'Account has beeen created succesfully.');
            }

            return back()->with('danger', $registerService->response->errors);
        }
    }
}
