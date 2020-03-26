<?php

namespace App\Services;

use App\Ninja\Repositories\PaymentTermRepository;
use App\Ninja\Datatables\PaymentTermDatatable;
use URL;

class PaymentTermService extends BaseService
{
    protected $paymentTermRepo;
    protected $datatableService;

    /**
     * PaymentTermService constructor.
     *
     * @param PaymentTermRepository $paymentTermRepo
     * @param DatatableService      $datatableService
     */
    public function __construct(PaymentTermRepository $paymentTermRepo, DatatableService $datatableService)
    {
        $this->paymentTermRepo = $paymentTermRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return PaymentTermRepository
     */
    protected function getRepo()
    {
        return $this->paymentTermRepo;
    }

    /**
     * @param int $accountId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($accountId = 0)
    {
        $datatable = new PaymentTermDatatable(false);

        $query = $this->paymentTermRepo->find($accountId);

        return $this->datatableService->createDatatable($datatable, $query);
    }

    public function columns($entityType, $hideClient)
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("payment_terms/{$model->public_id}/edit", $model->name)->toHtml();
                },
            ],
            [
                'days',
                function ($model) {
                    return $model->num_days;
                },
            ],
        ];
    }

    public function actions($entityType)
    {
        return [
            [
                uctrans('texts.edit_payment_terms'),
                function ($model) {
                    return URL::to("payment_terms/{$model->public_id}/edit");
                },
            ],
        ];
    }
}
