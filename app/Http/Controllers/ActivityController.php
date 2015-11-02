<?php namespace App\Http\Controllers;

use Auth;
use DB;
use Datatable;
use Utils;
use View;
use App\Models\Client;
use App\Models\Activity;
use App\Ninja\Repositories\ActivityRepository;

class ActivityController extends BaseController
{
    protected $activityRepo;

    public function __construct(ActivityRepository $activityRepo)
    {
        parent::__construct();

        $this->activityRepo = $activityRepo;
    }

    public function getDatatable($clientPublicId)
    {
        $clientId = Client::getPrivateId($clientPublicId);

        if ( ! $clientId) {
            app()->abort(404);
        }

        $query = $this->activityRepo->findByClientId($clientId);

        return Datatable::query($query)
            ->addColumn('activities.id', function ($model) { return Utils::timestampToDateTimeString(strtotime($model->created_at)); })
            ->addColumn('activity_type_id', function ($model) {
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
             })
            ->addColumn('balance', function ($model) { return Utils::formatMoney($model->balance, $model->currency_id); })
            ->addColumn('adjustment', function ($model) { return $model->adjustment != 0 ? Utils::wrapAdjustment($model->adjustment, $model->currency_id) : ''; })
            ->make();
    }
}
