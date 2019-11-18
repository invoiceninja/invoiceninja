<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CompaniesController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return view('migration.companies');
    }
}
