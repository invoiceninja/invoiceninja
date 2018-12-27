<?php

namespace App\Datatables;

use App\Models\Client;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientDatatable
{

	/**
	* ?sort=&page=1&per_page=20
	*/
	public static function query(Request $request, int $company_id)
	{
		/**
		*
		* $sort_col is returned col|asc
		* needs to be exploded
		*
		*/
		$sort_col = explode("|", $request->input('sort'));

		$data = self::find($company_id, $request->input('filter'))
						->orderBy($sort_col[0], $sort_col[1])
						->paginate($request->input('per_page'));

		return response()
					->json(self::buildActionColumn($data), 200);

	}


	private static function find(int $company_id, $filter, $userId = false)
	    {
	        $query = DB::table('clients')
	                    ->join('companies', 'companies.id', '=', 'clients.company_id')
	                    ->join('client_contacts', 'client_contacts.client_id', '=', 'clients.id')
	                    ->where('clients.company_id', '=', $company_id)
	                    ->where('client_contacts.is_primary', '=', true)
	                    ->where('client_contacts.deleted_at', '=', null)
	                    //->whereRaw('(clients.name != "" or contacts.first_name != "" or contacts.last_name != "" or contacts.email != "")') // filter out buy now invoices
	                    ->select(
	                        DB::raw('COALESCE(clients.currency_id, companies.currency_id) currency_id'),
	                        DB::raw('COALESCE(clients.country_id, companies.country_id) country_id'),
	                        DB::raw("CONCAT(COALESCE(client_contacts.first_name, ''), ' ', COALESCE(client_contacts.last_name, '')) contact"),
	                        'clients.id',
	                        'clients.name',
	                        'clients.private_notes',
	                        'client_contacts.first_name',
	                        'client_contacts.last_name',
	                        'clients.balance',
	                        'clients.last_login',
	                        'clients.created_at',
	                        'clients.created_at as client_created_at',
	                        'client_contacts.phone',
	                        'client_contacts.email',
	                        'clients.deleted_at',
	                        'clients.is_deleted',
	                        'clients.user_id',
	                        'clients.id_number'
	                    );
/*
	         if(Auth::user()->account->customFieldsOption('client1_filter')) {
	            $query->addSelect('clients.custom_value1');
	        }

	        if(Auth::user()->account->customFieldsOption('client2_filter')) {
	            $query->addSelect('clients.custom_value2');
	        }

	        $this->applyFilters($query, ENTITY_CLIENT);
*/
	        if ($filter) {
	            $query->where(function ($query) use ($filter) {
	                $query->where('clients.name', 'like', '%'.$filter.'%')
	                      ->orWhere('clients.id_number', 'like', '%'.$filter.'%')
	                      ->orWhere('client_contacts.first_name', 'like', '%'.$filter.'%')
	                      ->orWhere('client_contacts.last_name', 'like', '%'.$filter.'%')
	                      ->orWhere('client_contacts.email', 'like', '%'.$filter.'%');
	            });
/*
	            if(Auth::user()->account->customFieldsOption('client1_filter')) {
	                $query->orWhere('clients.custom_value1', 'like' , '%'.$filter.'%');
	            }

	            if(Auth::user()->account->customFieldsOption('client2_filter')) {
	                $query->orWhere('clients.custom_value2', 'like' , '%'.$filter.'%');
	            }
*/
	        }

	        if ($userId) {
	            $query->where('clients.user_id', '=', $userId);
	        }

	        return $query;
	    }

    private static function buildActionColumn($data)
    {

    	

    	$data->map(function ($row) {

    		$btn = '<div id="ddown-lg" class="m-2 btn-group b-dropdown dropdown"><!----><button id="ddown-lg__BV_toggle_" aria-haspopup="true" aria-expanded="false" type="button" class="btn btn-secondary btn-lg dropdown-toggle">Large</button><div role="menu" aria-labelledby="ddown-lg__BV_toggle_" class="dropdown-menu" style=""><button role="menuitem" type="button" class="dropdown-item">Action</button><button role="menuitem" type="button" class="dropdown-item">Another action</button><button role="menuitem" type="button" class="dropdown-item">Something else here</button></div></div>';

		    $row->action = $btn;
		    
		    return $row;
		});

		return $data;
    	
    }
}