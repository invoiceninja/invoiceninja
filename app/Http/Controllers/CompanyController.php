<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\SignupRequest;
use App\Jobs\Company\CreateCompany;
use App\Jobs\RegisterNewAccount;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Class CompanyController
 * @package App\Http\Controllers
 */
class CompanyController extends BaseController
{
    use DispatchesJobs;

    /**
     * CompanyController constructor.
     */
    public function __construct()
    {
    
        parent::__construct();

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('signup.index');

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\SignupRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateCompanyRequest $request)
    {

        CreateCompany::dispatchNow($request);

        //todo redirect to localization setup workflow
        return redirect()->route('dashboard.index');

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
