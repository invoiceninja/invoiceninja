<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Credits\ShowCreditRequest;
use App\Http\Requests\ClientPortal\Credits\ShowCreditsRequest;
use App\Models\Credit;

class CreditController extends Controller
{
    public function index(ShowCreditsRequest $request)
    {
        return $this->render('credits.index');
    }

    public function show(ShowCreditRequest $request, Credit $credit)
    {
        set_time_limit(0);

        $data = ['credit' => $credit];


        if ($request->query('mode') === 'fullscreen') {
            return render('credits.show-fullscreen', $data);
        }

        return $this->render('credits.show', $data);
    }
}
