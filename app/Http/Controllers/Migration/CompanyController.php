<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Migration\CompanySelectRequest;
use App\Services\Migration\CompanyService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CompanyController extends BaseController
{
    public function index()
    {
        $companies = (new CompanyService())->getCompanies()->data;

        return view('migration.company',
            compact('companies')
        );
    }

    public function store(CompanySelectRequest $request)
    {
        session()->put('company_id', $request->company);

        return redirect('/migration/steps/settings');
    }
}
