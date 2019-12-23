<?php

namespace App\Http\Controllers\Migration\Account;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Migration\Account\SelfRegisterRequest;
use App\Services\Migration\Account\RegisterService;
use Illuminate\Http\Request;
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

            if ($registerService->getSuccessful()) {
                return redirect('/migration/company')->with('message', 'Account has beeen created succesfully.');
            }

            if(isset($registerService->response->errors)) {
                $request->session()->flash('responseErrors', $registerService->response->errors);
            }

            return back()->with('message', $registerService->response->message);
        }
    }
}
