<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
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
     * @return array
     */
    public function store()
    {
        return \request()->all();
    }
}
