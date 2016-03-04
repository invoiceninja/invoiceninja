<?php namespace App\Services;

use URL;
use Auth;
use App\Services\BaseService;
use App\Ninja\Repositories\TaxRateRepository;

class TaxRateService extends BaseService
{
    protected $taxRateRepo;
    protected $datatableService;

    public function __construct(TaxRateRepository $taxRateRepo, DatatableService $datatableService)
    {
        $this->taxRateRepo = $taxRateRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->taxRateRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($accountId)
    {
        $query = $this->taxRateRepo->find($accountId);

        return $this->createDatatable(ENTITY_TAX_RATE, $query, false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("tax_rates/{$model->public_id}/edit", $model->name)->toHtml();
                }
            ],
            [
                'rate',
                function ($model) {
                    return $model->rate . '%';
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                uctrans('texts.edit_tax_rate'),
                function ($model) {
                    return URL::to("tax_rates/{$model->public_id}/edit");
                }
            ]
        ];
    }

}