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
        set_time_limit(0);

        $data = ['credit' => $credit];


        if ($request->query('mode') === 'fullscreen') {
            return render('credits.show-fullscreen', $data);
        }

        return $this->render('credits.show', $data);
    }
}
