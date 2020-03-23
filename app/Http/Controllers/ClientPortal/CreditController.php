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
        $credits = auth()->user()->company->credits()->paginate(10);

        return $this->render('credits.index', [
            'credits' => $credits,
        ]);
    }

    public function show(ShowCreditRequest $request, Credit $credit)
    {
        return $this->render('credits.show', [
            'credit' => $credit,
        ]);
    }
}
