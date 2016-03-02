<?php namespace App\Services;

use Utils;
use URL;
use Auth;
use App\Services\BaseService;
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

    public function save($data)
    {
        if (Auth::user()->account->isNinjaAccount() && isset($data['pro_plan_paid'])) {
            $this->ninjaRepo->updateProPlanPaid($data['public_id'], $data['pro_plan_paid']);
        }

        return $this->clientRepo->save($data);
    }

    public function getDatatable($search)
    {
        $query = $this->clientRepo->find($search);

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
                }
            ],
            [],
            [
                trans('texts.new_task'),
                function ($model) {
                    return URL::to("tasks/create/{$model->public_id}");
                }
            ],
            [
                trans('texts.new_invoice'),
                function ($model) {
                    return URL::to("invoices/create/{$model->public_id}");
                }
            ],
            [
                trans('texts.new_quote'),
                function ($model) {
                    return URL::to("quotes/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->isPro();
                }
            ],
            [],
            [
                trans('texts.enter_payment'),
                function ($model) {
                    return URL::to("payments/create/{$model->public_id}");
                }
            ],
            [
                trans('texts.enter_credit'),
                function ($model) {
                    return URL::to("credits/create/{$model->public_id}");
                }
            ],
            [
                trans('texts.enter_expense'),
                function ($model) {
                    return URL::to("expenses/create/0/{$model->public_id}");
                }
            ]
        ];
    }
}
