<?php namespace App\Services;

use Utils;
use URL;
use Auth;
use App\Services\BaseService;
use App\Ninja\Repositories\VendorRepository;
use App\Ninja\Repositories\NinjaRepository;

class VendorService extends BaseService
{
    protected $vendorRepo;
    protected $datatableService;

    public function __construct(VendorRepository $vendorRepo, DatatableService $datatableService, NinjaRepository $ninjaRepo)
    {
        $this->vendorRepo = $vendorRepo;
        $this->ninjaRepo = $ninjaRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->vendorRepo;
    }

    public function save($data)
    {
        if (Auth::user()->account->isNinjaAccount() && isset($data['pro_plan_paid'])) {
            $this->ninjaRepo->updateProPlanPaid($data['public_id'], $data['pro_plan_paid']);
        }

        return $this->vendorRepo->save($data);
    }

    public function getDatatable($search)
    {
        $query = $this->vendorRepo->find($search);

        return $this->createDatatable(ENTITY_VENDOR, $query);
    }

    protected function getDatatableColumns($entityType, $hideVendor)
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("vendors/{$model->public_id}", $model->name ?: '');
                }
            ],
            [
                'first_name',
                function ($model) {
                    return link_to("vendors/{$model->public_id}", $model->first_name.' '.$model->last_name);
                }
            ],
            [
                'email',
                function ($model) {
                    return link_to("vendors/{$model->public_id}", $model->email ?: '');
                }
            ],
            [
                'vendors.created_at',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->created_at));
                }
            ],
            /*[
                'last_login',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->last_login));
                }
            ],*/
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
                trans('texts.edit_vendor'),
                function ($model) {
                    return URL::to("vendors/{$model->public_id}/edit");
                }
            ],
            [],
            [
                trans('texts.enter_expense'),
                function ($model) {
                    return URL::to("expenses/create/{$model->public_id}");
                }
            ]
        ];
    }
}
