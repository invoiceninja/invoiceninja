<?php namespace App\Services;

use URL;
use App\Models\Gateway;
use App\Services\BaseService;
use App\Ninja\Repositories\AccountGatewayRepository;

class AccountGatewayService extends BaseService
{
    protected $accountGatewayRepo;
    protected $datatableService;

    public function __construct(AccountGatewayRepository $accountGatewayRepo, DatatableService $datatableService)
    {
        $this->accountGatewayRepo = $accountGatewayRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->accountGatewayRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($accountId)
    {
        $query = $this->accountGatewayRepo->find($accountId);

        return $this->createDatatable(ENTITY_ACCOUNT_GATEWAY, $query, false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("gateways/{$model->public_id}/edit", $model->name)->toHtml();
                }
            ],
            [
                'payment_type',
                function ($model) {
                    return Gateway::getPrettyPaymentType($model->gateway_id);
                }
            ],
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                uctrans('texts.edit_gateway'),
                function ($model) {
                    return URL::to("gateways/{$model->public_id}/edit");
                }
            ]
        ];
    }

}