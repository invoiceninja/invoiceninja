<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Migration\SelfLoginRequest;
use App\Services\Migration\Authentication;
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
     * @param SelfLoginRequest $request
     * @param $type
     * @return string
     */
    public function login(SelfLoginRequest $request, $type)
    {
        $authentication = new Authentication($request->all());
        $authentication->handle();

        if ($authentication->wasSuccessful()) {
            return back()->with('message', 'Authentication was successful.');
        }

        return back()->with('message', $authentication->response);
    }
}
