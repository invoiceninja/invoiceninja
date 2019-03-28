<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Class ContactController
 * @package App\Http\Controllers
 */
class ContactController extends BaseController
{
    /**
     * ContactController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->middleware('auth:contact');
    }


    /**
     * show dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('contact.index');
    }
}
