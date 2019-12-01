<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CompanyController extends BaseController
{
    public function index()
    {
        return view('migration.company',
            compact('companies')
        );
    }
}
