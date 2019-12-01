<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Services\Migration\CompanyService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CompanyController extends BaseController
{
    public function index()
    {
        $companies = (new CompanyService())->getCompanies();

        dd($companies);

        return view('migration.company',
            compact('companies')
        );
    }
}
