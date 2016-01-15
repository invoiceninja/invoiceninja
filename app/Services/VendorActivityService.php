<?php namespace App\Services;
// vendor
use Utils;
use App\Models\Vendor;
use App\Services\BaseService;
use App\Ninja\Repositories\VendorActivityRepository;

class VendorActivityService extends BaseService
{
    protected $activityRepo;
    protected $datatableService;

    public function __construct(VendorActivityRepository $activityRepo, DatatableService $datatableService)
    {
        $this->activityRepo = $activityRepo;
        $this->datatableService = $datatableService;
    }

    public function getDatatable($vendorPublicId = null)
    {
        $vendorId = Vendor::getPrivateId($vendorPublicId);

        $query = $this->activityRepo->findByVendorId($vendorId);

        return $this->createDatatable(ENTITY_ACTIVITY, $query);
    }

    protected function getDatatableColumns($entityType, $hideVendor)
    {
        return [
            [
                'vendor_activities.id',
                function ($model) {
                    return Utils::timestampToDateTimeString(strtotime($model->created_at));
                }
            ],
            [
                'activity_type_id',
                function ($model) {
                    $data = [
                        'vendor' => link_to('/vendors/' . $model->vendor_public_id, Utils::getVendorDisplayName($model)),
                        'user' => $model->is_system ? '<i>' . trans('texts.system') . '</i>' : Utils::getPersonDisplayName($model->user_first_name, $model->user_last_name, $model->user_email), 
                        'invoice' => $model->invoice ? link_to('/invoices/' . $model->invoice_public_id, $model->is_recurring ? trans('texts.recurring_invoice') : $model->invoice) : null,
                        'quote' => $model->invoice ? link_to('/quotes/' . $model->invoice_public_id, $model->invoice) : null,
                        'contact' => $model->contact_id ? link_to('/vendors/' . $model->vendor_public_id, Utils::getVendorDisplayName($model)) : Utils::getPersonDisplayName($model->user_first_name, $model->user_last_name, $model->user_email),
                        'payment' => $model->payment ?: '',
                        'credit' => Utils::formatMoney($model->credit, $model->currency_id, $model->country_id)
                    ];

                    return trans("texts.activity_{$model->activity_type_id}", $data);
                }
            ],
            [
                'balance',
                function ($model) {
                    return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                }
            ],
            [
                'adjustment',
                function ($model) {
                    return $model->adjustment != 0 ? Utils::wrapAdjustment($model->adjustment, $model->currency_id, $model->country_id) : '';
                }
            ]
        ];
    }
}