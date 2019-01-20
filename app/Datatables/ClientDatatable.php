<?php

namespace App\Datatables;

use App\Filters\ClientFilters;
use App\Models\Client;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ClientDatatable extends EntityDatatable
{
    use MakesHash;
    use MakesActionMenu;

    protected $filter;

    public function __construct(ClientFilters $filter)
    {
        $this->filter = $filter;
    }
    /**
     * Returns paginated results for the datatable
     *
     */
    public function query(Request $request, int $company_id)
    {
        $data = $this->filter->apply($company_id, auth()->user())->paginate($request->input('per_page'));

        return response()->json($this->buildActionColumn($data), 200);

    }

    
    private function find(int $company_id, $userId = false)
    {
    /*
         if(Auth::user()->account->customFieldsOption('client1_filter')) {
            $query->addSelect('clients.custom_value1');
        }

        if(Auth::user()->account->customFieldsOption('client2_filter')) {
            $query->addSelect('clients.custom_value2');
        }

        $this->applyFilters($query, ENTITY_CLIENT);

            if(Auth::user()->account->customFieldsOption('client1_filter')) {
                $query->orWhere('clients.custom_value1', 'like' , '%'.$filter.'%');
            }

            if(Auth::user()->account->customFieldsOption('client2_filter')) {
                $query->orWhere('clients.custom_value2', 'like' , '%'.$filter.'%');
            }

        }
*/


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

        //if(auth()->user()->isAdmin())
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

        $permissions = auth()->user()->permissions();

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

    public function listActions() : Collection
    {
      return collect([
        'multi_select' => [
            ['name' => trans('texts.active'), 'value' => 'active'],
            ['name' => trans('texts.archived'), 'value' => 'archived'],
            ['name' => trans('texts.deleted'), 'value' => 'deleted']
          ],
        'create_entity' => [
          'create_permission' => auth()->user()->can('create', Client::class),
          'url' => route('clients.create'),
          'name' => trans('texts.new_client')
        ]
      ]);
    }

    public function buildOptions() : Collection
    {

      $visible = auth()->user()->getColumnVisibility(Client::class);

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
                  'visible' => $visible->name,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'contact',
                  'title' => trans('texts.contact'),
                  'sortField' => 'contact',
                  'visible' => $visible->contact,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'email',
                  'title' => trans('texts.email'),
                  'sortField' => 'email',
                  'visible' => $visible->email,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'client_created_at',
                  'title' => trans('texts.date_created'),
                  'sortField' => 'client_created_at',
                  'visible' => $visible->client_created_at,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'last_login',
                  'title' => trans('texts.last_login'),
                  'sortField' => 'last_login',
                  'visible' => $visible->last_login,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'balance',
                  'title' => trans('texts.balance'),
                  'sortField' => 'balance',
                  'visible' => $visible->balance,
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