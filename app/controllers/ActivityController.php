<?php

class ActivityController extends \BaseController {

	public function getDatatable($clientPublicId)
    {
        $query = DB::table('activities')
                    ->join('clients', 'clients.id', '=', 'activities.client_id')
                    ->where('clients.public_id', '=', $clientPublicId)
                    ->where('activities.account_id', '=', Auth::user()->account_id)
                    ->select('activities.message', 'activities.created_at', 'activities.currency_id', 'activities.balance', 'activities.adjustment');
    	
        return Datatable::query($query)
    	    ->addColumn('created_at', function($model) { return Utils::timestampToDateTimeString(strtotime($model->created_at)); })
            ->addColumn('message', function($model) { return Utils::decodeActivity($model->message); })
            ->addColumn('balance', function($model) { return Utils::formatMoney($model->balance, $model->currency_id); })
            ->addColumn('adjustment', function($model) { return $model->adjustment != 0 ? Utils::formatMoney($model->adjustment, $model->currency_id) : ''; })
    	    ->make();
    }	

}