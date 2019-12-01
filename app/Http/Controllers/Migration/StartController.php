<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Migration\Start\SelectRequest;

class StartController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function __invoke()
    {
        return view('migration.start');
    }

    /**
     * @param SelectRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function select(SelectRequest $request)
    {
        if(!in_array($request->version, ['hosted', 'self_hosted'])) {
            return back()->with('danger', 'You have to select one of available options.');
        }

        session()->put('version', $request->version);

        return redirect('/migration/account');
    }
}
