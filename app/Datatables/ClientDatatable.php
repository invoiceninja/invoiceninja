<?php

namespace App\Datatables;

use App\Models\Client;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientDatatable
{

	/**
	* ?sort=&page=1&per_page=20
	*/
	public static function query(Request $request, int $company_id)
	{
		$sort_col = explode("|", $request->input('sort'));

		return response()->json(Client::where('company_id', '=', $company_id)->orderBy($sort_col[0], $sort_col[1])->paginate($request->input('per_page')), 200);
	}

}