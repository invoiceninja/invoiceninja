<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StartController extends BaseController
{
    public function __invoke()
    {
        return 200;
    }
}
