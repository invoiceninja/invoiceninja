<?php

class ActivityController extends \BaseController {

	public function getDatatable($clientId)
    {
        return Datatable::collection(Activity::scope()->where('client_id','=',$clientId)->get())
    	    ->addColumn('date', function($model) { return timestampToDateTimeString($model->created_at); })
            ->addColumn('message', function($model) { return $model->message; })
            ->addColumn('balance', function($model) { return '$' . $model->balance; })
            ->orderColumns('date')
    	    ->make();
    }	

}