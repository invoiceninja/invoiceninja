<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Migration\LoginRequest;
use App\Http\Requests\Migration\RegisterRequest;
use App\Services\Migration\LoginService;
use App\Services\Migration\RegisterService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CredentialsController extends BaseController
{
    /**
     * @param $type
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function index($type)
    {
        if (!in_array($type, ['hosted', 'self_hosted'])) {
            return abort(404);
        }

        return view(
            sprintf('migration.%s.account', $type)
        );
    }

    /**
     * @param LoginRequest $request
     * @param $type
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(LoginRequest $request, $type)
    {
        $authentication = new LoginService($request->all());
        $authentication->handle();

        if ($authentication->wasSuccessful()) {
            return redirect('/migration/companies')->with('success', 'Authentication was successful.');
        }

        return back()->with('failure', 'Oops, looks like that combination didn\'t worked. Please try again.');
    }

    /**
     * @param $type
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create($type)
    {
        return view(
            sprintf('migration.%s.account.create', $type)
        );
    }

    /**
     * @param RegisterRequest $request
     * @param $type
     * @return \Illuminate\Http\RedirectResponse
     */
    public function register(RegisterRequest $request, $type)
    {
        $registration = new RegisterService($request->all());
        $registration->handle();

        if ($registration->wasSuccessful()) {
            return redirect('/migration/companies')->with('success', 'Account was successfully created.');
        }

        return back()->with('errors', $registration->response->errors);
    }
}
