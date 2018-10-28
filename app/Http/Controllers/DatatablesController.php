<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Yajra\DataTables\Facades\DataTables;

class DatatablesController extends Controller
{

    /**
     * Process datatables ajax request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function anyData()
    {
        return Datatables::of(Client::all())->removeColumn('website')->make(true);
    }
}
