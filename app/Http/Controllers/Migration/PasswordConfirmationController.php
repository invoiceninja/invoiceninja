<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Migration\LoginRequest;
use App\Http\Requests\Migration\PasswordConfirmationRequest;
use App\Services\Migration\LoginService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PasswordConfirmationController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return view('migration.password_confirmation');
    }

    public function verify(PasswordConfirmationRequest $request)
    {
        $data = [
            'email_address' => session('email-address'),
            'password' => $request->password,
            'x_api_secret' => session('x-api-secret'),
            'api_endpoint' => session('api-endpoint'),
        ];

        $authentication = new LoginService($data);
        $authentication->handle();

        if ($authentication->wasSuccessful()) {
            return 'Authentication was successful!';
        }

        return back()->with('failure', 'Oops, looks like that didn\'t worked. Please try again.');
    }
}
