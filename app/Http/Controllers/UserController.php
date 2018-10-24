<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\VerifiesUserEmail;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use VerifiesUserEmail;


}
