<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('home');
    }

    public function user()
    {
        $this->middleware('auth:user');

        return view('dashboard.index');
    }

    public function signup()
    {
        return view('signup.index');
    }

    public function processSignup(SignupRequest $request)
    {
        dd($request->validated());
    }

}
