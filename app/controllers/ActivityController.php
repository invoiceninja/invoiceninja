<?php

class ActivityController extends \BaseController {

	public function getDatatable($clientId)
    {
        return Datatable::collection(Activity::where('account_id','=',Auth::user()->account_id)
        	->where('client_id','=',$clientId)->get())
    	    ->addColumn('date', function($model) { return $model->created_at->format('m/d/y h:i a'); })
            ->addColumn('message', function($model) { return $model->message; })
            ->addColumn('balance', function($model) { return '$' . $model->balance; })
            ->orderColumns('date')
    	    ->make();
    }	

}