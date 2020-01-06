<?php

namespace App\Http\Controllers\Migration;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseController;

class StepsController extends BaseController
{
    public function start()
    {
        return view('migration.start'); 
    }
}
