<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\VerifiesUserEmail;
use Illuminate\Http\Request;

/**
 * Class UserController
 * @package App\Http\Controllers
 */
class UserController extends Controller
{
    use VerifiesUserEmail;


}
