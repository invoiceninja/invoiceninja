<?php namespace App\Services;

use Utils;
use URL;
use Auth;
use App\Services\BaseService;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Task;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\NinjaRepository;

class ClientService extends BaseService
{
    protected $clientRepo;
    protected $datatableService;

    public function __construct(ClientRepository $clientRepo, DatatableService $datatableService, NinjaRepository $ninjaRepo)
    {
        $this->clientRepo = $clientRepo;
        $this->ninjaRepo = $ninjaRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->clientRepo;
    }

    public function save($data, $client = null)
    {
        if (Auth::user()->account->isNinjaAccount() && isset($data['plan'])) {
            $this->ninjaRepo->updatePlanDetails($data['public_id'], $data);
        }

        return $this->clientRepo->save($data, $client);
    }

    public function getDatatable($search)
    {
        $query = $this->clientRepo->find($search);

        if(!Utils::hasPermission('view_all')){
            $query->where('clients.user_id', '=', Auth::user()->id);
        }

        return $this->createDatatable(ENTITY_CLIENT, $query);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("clients/{$model->public_id}", $model->name ?: '')->toHtml();
                }
            ],
            [
                'first_name',
                function ($model) {
                    return link_to("clients/{$model->public_id}", $model->first_name.' '.$model->last_name)->toHtml();
                }
            ],
            [
                'email',
                function ($model) {
                    return link_to("clients/{$model->public_id}", $model->email ?: '')->toHtml();
                }
            ],
            [
                'clients.created_at',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->created_at));
                }
            ],
            [
                'last_login',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->last_login));
                }
            ],
            [
                'balance',
                function ($model) {
                    return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_client'),
                function ($model) {
                    return URL::to("clients/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_CLIENT, $model->user_id]);
                }
            ],
            [
                '--divider--', function(){return false;},
                function ($model) {
                    $user = Auth::user();
                    return $user->can('editByOwner', [ENTITY_CLIENT, $model->user_id]) && ($user->can('create', ENTITY_TASK) || $user->can('create', ENTITY_INVOICE));
                }
            ],
            [
                trans('texts.new_task'),
                function ($model) {
                    return URL::to("tasks/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_TASK);
                }
            ],
            [
                trans('texts.new_invoice'),
                function ($model) {
                    return URL::to("invoices/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_INVOICE);
                }
            ],
            [
                trans('texts.new_quote'),
                function ($model) {
                    return URL::to("quotes/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->hasFeature(FEATURE_QUOTES) && Auth::user()->can('create', ENTITY_INVOICE);
                }
            ],
            [
                '--divider--', function(){return false;},
                function ($model) {
                    $user = Auth::user();
                    return ($user->can('create', ENTITY_TASK) || $user->can('create', ENTITY_INVOICE)) && ($user->can('create', ENTITY_PAYMENT) || $user->can('create', ENTITY_CREDIT) || $user->can('create', ENTITY_EXPENSE));
                }
            ],
            [
                trans('texts.enter_payment'),
                function ($model) {
                    return URL::to("payments/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_PAYMENT);
                }
            ],
            [
                trans('texts.enter_credit'),
                function ($model) {
                    return URL::to("credits/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_CREDIT);
                }
            ],
            [
                trans('texts.enter_expense'),
                function ($model) {
                    return URL::to("expenses/create/0/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_EXPENSE);
                }
            ]
        ];
    }
}
