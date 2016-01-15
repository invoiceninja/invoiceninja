<?php namespace App\Models;

use Auth;
use Eloquent;
use Utils;
use Session;
use Request;
use Carbon;

class ExpenseActivity extends Eloquent {
    // Expenses
    public $timestamps = true;

    public function scopeScope($query)
    {
        return $query->whereAccountId(Auth::user()->account_id);
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo('App\Models\Vendor')->withTrashed();
    }

    public function expense()
    {
        return $this->belongsTo('App\Models\Expense')->withTrashed();
    }
    
    public function getMessage()
    {
        $activityTypeId = $this->activity_type_id;
        $account        = $this->account;
        $vendor         = $this->vendor;
        $user           = $this->user;
        $contactId      = $this->contact_id;
        $isSystem       = $this->is_system;
        $expense        = $this->expense;

        if($expense)
        {
            $route = link_to($expense->getRoute(), $expense->getDisplayName());
        } else {
            $route ='no expense id';
        }

        $data = [
                'expense' => $route,
                'user' => $isSystem ? '<i>' . trans('texts.system') . '</i>' : $user->getDisplayName(),
            ];

        return trans("texts.activity_{$activityTypeId}", $data);
    }	
}
