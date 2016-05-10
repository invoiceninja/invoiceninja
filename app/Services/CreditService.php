<?php namespace App\Services;

use Utils;
use URL;
use Auth;
use App\Services\BaseService;
use App\Models\Client;
use App\Models\Payment;
use App\Ninja\Repositories\CreditRepository;


class CreditService extends BaseService
{
    protected $creditRepo;
    protected $datatableService;

    public function __construct(CreditRepository $creditRepo, DatatableService $datatableService)
    {
        $this->creditRepo = $creditRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->creditRepo;
    }

    public function save($data)
    {
        return $this->creditRepo->save($data);
    }

    public function getDatatable($clientPublicId, $search)
    {
        $query = $this->creditRepo->find($clientPublicId, $search);
        
        if(!Utils::hasPermission('view_all')){
            $query->where('credits.user_id', '=', Auth::user()->id);
        }

        return $this->createDatatable(ENTITY_CREDIT, $query, !$clientPublicId);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'client_name',
                function ($model) {
                    if(!Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])){
                        return Utils::getClientDisplayName($model);
                    }
                    
                    return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml() : '';
                },
                ! $hideClient
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id) . '<span '.Utils::getEntityRowClass($model).'/>';
                }
            ],
            [
                'balance',
                function ($model) {
                    return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                }
            ],
            [
                'credit_date',
                function ($model) {
                    return Utils::fromSqlDate($model->credit_date);
                }
            ],
            [
                'private_notes',
                function ($model) {
                    return $model->private_notes;
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.apply_credit'),
                function ($model) {
                    return URL::to("payments/create/{$model->client_public_id}") . '?paymentTypeId=1';
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_PAYMENT);
                }
            ]
        ];
    }
}