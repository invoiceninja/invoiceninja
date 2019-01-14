<?php

namespace App\Datatables;

use App\Models\Client;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientDatatable extends EntityDatatable
{
    use MakesHash;
    use MakesActionMenu;

    /**
    * ?sort=&page=1&per_page=20
    */
    public function query(Request $request, int $company_id)
    {
        /**
        *
        * $sort_col is returned col|asc
        * needs to be exploded
        *
        */
        $sort_col = explode("|", $request->input('sort'));

        $data = $this->find($company_id, $request->input('filter'))
                        ->orderBy($sort_col[0], $sort_col[1])
                        ->paginate($request->input('per_page'));

        return response()
                    ->json($this->buildActionColumn($data), 200);

    }


    private function find(int $company_id, $filter, $userId = false)
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

    /**
     * Returns the action dropdown menu 
     * 
     * @param   $data   Std Class of client datatable rows
     * @return  object  Rendered action column items
     */
    private function buildActionColumn($data)
    {

        //if(auth()->user()->is_admin())
        //todo permissions are only mocked here, when user permissions have been implemented this needs to be refactored.
        
        $permissions = [
            'view_client', 
            'edit_client', 
            'create_task', 
            'create_invoice', 
            'create_payment', 
            'create_credit', 
            'create_expense'
            ];

        $requested_actions = [
            'view_client_client_id', 
            'edit_client_client_id', 
            'create_task_client_id', 
            'create_invoice_client_id', 
            'create_payment_client_id', 
            'create_credit_client_id', 
            'create_expense_client_id'
        ];

        $is_admin = false;

        $actions = $this->filterActions($requested_actions, $permissions, $is_admin);

        $data->map(function ($row) use ($actions) {

            $updated_actions = $actions->map(function ($action) use($row){

                $action['url'] = route($action['route'], [$action['key'] => $this->encodePrimaryKey($row->id)]);
                return $action;

            });

            $row->actions = $updated_actions;

            return $row;
        });

        return $data;
        
    }

    public function buildOptions($settings)
    {
        return collect([
            'per_page' => 20,
            'sort_order' => [
                [
                  'field' => 'name',
                  'sortField' => 'name',
                  'direction' => 'asc',
                ]
            ],
            'fields' => [
                [
                  'name' => '__checkbox',   
                  'title' => '',
                  'titleClass' => 'center aligned',
                  'visible' => true,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'name',
                  'title' => trans('texts.name'),
                  'sortField' => 'name',
                  'visible' => true,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'contact',
                  'title' => trans('texts.contact'),
                  'sortField' => 'contact',
                  'visible' => true,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'email',
                  'title' => trans('texts.email'),
                  'sortField' => 'email',
                  'visible' => true,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'client_created_at',
                  'title' => trans('texts.date_created'),
                  'sortField' => 'client_created_at',
                  'visible' => true,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'last_login',
                  'title' => trans('texts.last_login'),
                  'sortField' => 'last_login',
                  'visible' => true,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'balance',
                  'title' => trans('texts.balance'),
                  'sortField' => 'balance',
                  'visible' => true,
                  'dataClass' => 'center aligned'             
                ],
                [
                  'name' => '__component:client-actions',   
                  'title' => '',
                  'titleClass' => 'center aligned',
                  'visible' => true,
                  'dataClass' => 'center aligned'
                ]
            ]
        ]);
    }

}