<?php namespace App\Http\Controllers;

use Auth;
use DB;
use Datatable;
use Utils;
use View;

class ActivityController extends BaseController
{
    public function getDatatable($clientPublicId)
    {
        $query = DB::table('activities')
                    ->join('clients', 'clients.id', '=', 'activities.client_id')
                    ->where('clients.public_id', '=', $clientPublicId)
                    ->where('activities.account_id', '=', Auth::user()->account_id)
                    ->select('activities.id', 'activities.message', 'activities.created_at', 'clients.currency_id', 'activities.balance', 'activities.adjustment');

        return Datatable::query($query)
            ->addColumn('activities.id', function ($model) { return Utils::timestampToDateTimeString(strtotime($model->created_at)); })
            ->addColumn('message', function ($model) { return Utils::decodeActivity($model->message); })
            ->addColumn('balance', function ($model) { return Utils::formatMoney($model->balance, $model->currency_id); })
            ->addColumn('adjustment', function ($model) { return $model->adjustment != 0 ? self::wrapAdjustment($model->adjustment, $model->currency_id) : ''; })
            ->make();
    }

    private function wrapAdjustment($adjustment, $currencyId)
    {
        $class = $adjustment <= 0 ? 'success' : 'default';
        $adjustment = Utils::formatMoney($adjustment, $currencyId);
        return "<h4><div class=\"label label-{$class}\">$adjustment</div></h4>";
    }
}
