<?php

namespace App\Http\Controllers;

use App\Utils\Traits\MakesHeaderData;

class DashboardController extends Controller
{
    use MakesHeaderData;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user');

    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data['header'] = $this->metaData();
dd($data);
        return view('dashboard.index', $data);
    }


}
