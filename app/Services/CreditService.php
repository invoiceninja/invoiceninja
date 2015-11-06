<?php namespace App\Services;

use Utils;
use URL;
use App\Services\BaseService;
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

        return $this->createDatatable(ENTITY_CREDIT, $query, !$clientPublicId);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'client_name',
                function ($model) {
                    return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model)) : '';
                },
                ! $hideClient
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id) . '<span '.Utils::getEntityRowClass($model).'/>';
                }
            ],
            [
                'balance',
                function ($model) {
                    return Utils::formatMoney($model->balance, $model->currency_id);
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
                }
            ]
        ];
    }
}