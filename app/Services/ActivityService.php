<?php namespace App\Services;

use Utils;
use App\Models\Client;
use App\Services\BaseService;
use App\Ninja\Repositories\ActivityRepository;

class ActivityService extends BaseService
{
    protected $activityRepo;
    protected $datatableService;

    public function __construct(ActivityRepository $activityRepo, DatatableService $datatableService)
    {
        $this->activityRepo = $activityRepo;
        $this->datatableService = $datatableService;
    }

    public function getDatatable($clientPublicId = null)
    {
        $query = $this->activityRepo->findByClientPublicId($clientPublicId);

        return $this->createDatatable(ENTITY_ACTIVITY, $query);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'activities.id',
                function ($model) {
                    return Utils::timestampToDateTimeString(strtotime($model->created_at));
                }
            ],
            [
                'activity_type_id',
                function ($model) {
                    $data = [
                        'client' => link_to('/clients/' . $model->client_public_id, Utils::getClientDisplayName($model)),
                        'user' => $model->is_system ? '<i>' . trans('texts.system') . '</i>' : Utils::getPersonDisplayName($model->user_first_name, $model->user_last_name, $model->user_email), 
                        'invoice' => $model->invoice ? link_to('/invoices/' . $model->invoice_public_id, $model->is_recurring ? trans('texts.recurring_invoice') : $model->invoice) : null,
                        'quote' => $model->invoice ? link_to('/quotes/' . $model->invoice_public_id, $model->invoice) : null,
                        'contact' => $model->contact_id ? link_to('/clients/' . $model->client_public_id, Utils::getClientDisplayName($model)) : Utils::getPersonDisplayName($model->user_first_name, $model->user_last_name, $model->user_email),
                        'payment' => $model->payment ?: '',
                        'credit' => Utils::formatMoney($model->credit, $model->currency_id)
                    ];

                    return trans("texts.activity_{$model->activity_type_id}", $data);
                }
            ],
            [
                'balance',
                function ($model) {
                    return Utils::formatMoney($model->balance, $model->currency_id);
                }
            ],
            [
                'adjustment',
                function ($model) {
                    return $model->adjustment != 0 ? Utils::wrapAdjustment($model->adjustment, $model->currency_id) : '';
                }
            ]
        ];
    }
}