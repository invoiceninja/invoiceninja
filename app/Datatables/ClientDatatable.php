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
     * @param   $rows   Std Class of client datatable rows
     * @return  object  Rendered action column items
     */
    private function buildActionColumn($rows)
    {

      $requested_actions = [
          'view_client_client_id', 
          'edit_client_client_id', 
          'create_task_client_id', 
          'create_invoice_client_id', 
          'create_payment_client_id', 
          'create_credit_client_id', 
          'create_expense_client_id'
      ];

      /*
       * Build a collection of action
       */
      $rows = $this->processActions($requested_actions, $rows, Client::class);

      /*
       * Add a _view_ link directly to the client
       */
      $rows->map(function($row){

        $row->name = '<a href="' . route('clients.show', ['id' => $this->encodePrimaryKey($row->id)]) . '">' . $row->name . '</a>';
        return $row;

      });

      return $rows;
        
    }

    /**
     * Returns a collection of helper fields
     * for the Client List Datatable
     * 
     * @return Collection collection
     */
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

    /**
     * Returns the Datatable settings including column visibility
     *     
     * @return Collection collection
     */
    public function buildOptions() : Collection
    {

      $visible = auth()->user()->getColumnVisibility(Client::class);

        return collect([
            'per_page' => 25,
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