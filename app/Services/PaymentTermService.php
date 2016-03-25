<?php namespace App\Services;

use URL;
use Auth;
use App\Services\BaseService;
use App\Ninja\Repositories\PaymentTermRepository;

class PaymentTermService extends BaseService
{
    protected $paymentTermRepo;
    protected $datatableService;

    public function __construct(PaymentTermRepository $paymentTermRepo, DatatableService $datatableService)
    {
        $this->paymentTermRepo = $paymentTermRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->paymentTermRepo;
    }

    public function getDatatable($accountId = 0)
    {
        $query = $this->paymentTermRepo->find();

        return $this->createDatatable(ENTITY_PAYMENT_TERM, $query, false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("payment_terms/{$model->public_id}/edit", $model->name)->toHtml();
                }
            ],
            [
                'days',
                function ($model) {
                    return $model->num_days;
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                uctrans('texts.edit_payment_terms'),
                function ($model) {
                    return URL::to("payment_terms/{$model->public_id}/edit");
                }
            ]
        ];
    }
}