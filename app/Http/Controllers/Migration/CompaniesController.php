<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Migration\MigrationOptionRequest;
use App\Services\Migration\CompaniesService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CompaniesController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $service = new CompaniesService();
        $service->get();

        return view('migration.companies', [
            'companies' => $service->getCompanies(),
        ]);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(MigrationOptionRequest $request)
    {
        /** Save migration option for future use. */
        session(['migration_type' => $request->migration_option]);

        return redirect('/migration/password_confirmation');
    }
}
