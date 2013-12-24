<?php

class ActivityController extends \BaseController {

	public function getDatatable($clientPublicId)
    {
    	$clientId = Client::getPrivateId($clientPublicId);
    	
        return Datatable::collection(Activity::scope()->where('client_id','=',$clientId)->get())
    	    ->addColumn('date', function($model) { return Utils::timestampToDateTimeString(strtotime($model->created_at)); })
            ->addColumn('message', function($model) { return $model->message; })
            ->addColumn('balance', function($model) { return '$' . $model->balance; })
            ->orderColumns('date')
    	    ->make();
    }	

}