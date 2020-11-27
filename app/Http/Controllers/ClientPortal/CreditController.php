<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\ShowCreditRequest;
use App\Models\Credit;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

class CreditController extends Controller
{
    /**
     * Display listing of client credits.
     *
     * @return Factory|View
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
