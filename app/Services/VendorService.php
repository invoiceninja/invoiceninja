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
        $this->vendorRepo       = $vendorRepo;
        $this->ninjaRepo        = $ninjaRepo;
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
                    return link_to("vendors/{$model->public_id}", $model->name ?: '')->toHtml();
                }
            ],
            [
                'city',
                function ($model) {
                    return $model->city;
                }
            ],
            [
                'work_phone',
                function ($model) {
                    return $model->work_phone;
                }
            ],
            [
                'email',
                function ($model) {
                    return link_to("vendors/{$model->public_id}", $model->email ?: '')->toHtml();
                }
            ],
            [
                'vendors.created_at',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->created_at));
                }
            ],
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
