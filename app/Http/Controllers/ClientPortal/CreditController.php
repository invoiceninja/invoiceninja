<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\ShowCreditRequest;
use App\Models\Credit;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    /**
     * Display listing of client credits.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return $this->render('credits.index');
    }

    public function show(ShowCreditRequest $request, Credit $credit)
    {
        return $this->render('credits.show', [
            'credit' => $credit,
        ]);
    }
}
