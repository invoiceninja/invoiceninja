<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CredentialsController extends BaseController
{
    public function index($type)
    {
        return 'Credentials bro..' . $type;
    }
}
