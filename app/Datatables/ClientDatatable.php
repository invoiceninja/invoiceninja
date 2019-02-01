<?php

namespace App\Datatables;

use App\Datatables\MakesActionMenu;
use App\Filters\ClientFilters;
use App\Models\Client;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class ClientDatatable
 * @package App\Datatables
 */
class ClientDatatable extends EntityDatatable
{
    use MakesHash;
    use MakesActionMenu;

    /**
     * @var ClientFilters
     */
    protected $filter;

    /**
     * ClientDatatable constructor.
     * @param ClientFilters $filter
     */
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
      $rows = $this->processActionsForDatatable($requested_actions, $rows, Client::class);

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
            ['name' => ctrans('texts.active'), 'value' => 'active'],
            ['name' => ctrans('texts.archived'), 'value' => 'archived'],
            ['name' => ctrans('texts.deleted'), 'value' => 'deleted']
          ],
        'create_entity' => [
          'create_permission' => auth()->user()->can('create', Client::class),
          'url' => route('clients.create'),
          'name' => ctrans('texts.new_client')
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
      $company = auth()->user()->company();

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
                  'title' => ctrans('texts.name'),
                  'sortField' => 'name',
                  'visible' => $visible->name,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'contact',
                  'title' => ctrans('texts.contact'),
                  'sortField' => 'contact',
                  'visible' => $visible->contact,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'email',
                  'title' => ctrans('texts.email'),
                  'sortField' => 'email',
                  'visible' => $visible->email,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'client_created_at',
                  'title' => ctrans('texts.date_created'),
                  'sortField' => 'client_created_at',
                  'visible' => $visible->client_created_at,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'last_login',
                  'title' => ctrans('texts.last_login'),
                  'sortField' => 'last_login',
                  'visible' => $visible->last_login,
                  'dataClass' => 'center aligned'
                ],
                [
                  'name' => 'balance',
                  'title' => ctrans('texts.balance'),
                  'sortField' => 'balance',
                  'visible' => $visible->balance,
                  'dataClass' => 'center aligned'             
                ],
                [
                  'name' => 'custom_value1',
                  'title' => $company->custom_client_label1 ?: '',
                  'sortField' => 'custom_value1',
                  'visible' => $visible->custom_value1,
                  'dataClass' => 'center aligned'             
                ],
                [
                  'name' => 'custom_value2',
                  'title' => $company->custom_client_label2 ?: '',
                  'sortField' => 'custom_value2',
                  'visible' => $visible->custom_value2,
                  'dataClass' => 'center aligned'             
                ],
                                [
                  'name' => 'custom_value3',
                  'title' => $company->custom_client_label3 ?: '',
                  'sortField' => 'custom_value3',
                  'visible' => $visible->custom_value3,
                  'dataClass' => 'center aligned'             
                ],
                                [
                  'name' => 'custom_value4',
                  'title' => $company->custom_client_label4 ?: '',
                  'sortField' => 'custom_value4',
                  'visible' => $visible->custom_value4,
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