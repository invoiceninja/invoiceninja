<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;

/**
 * Class SignupController
 * @package App\Http\Controllers
 */
class SignupController extends Controller
{

    /**
     * SignupController constructor.
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function signup()
    {
        return view('signup.index');
    }

    /**
     * @param SignupRequest $request
     */
    public function processSignup(SignupRequest $request)
    {
        dd($request->validated());
    }

}