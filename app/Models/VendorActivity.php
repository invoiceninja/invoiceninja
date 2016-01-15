<?php namespace App\Models;
// vendor
use Auth;
use Eloquent;
use Utils;
use Session;
use Request;
use Carbon;

class VendorActivity extends Eloquent {

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

    public function vendorContact()
    {
        return $this->belongsTo('App\Models\VendorContact')->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo('App\Models\Vendor')->withTrashed();
    }

    public function getMessage()
    {
        $activityTypeId = $this->activity_type_id;
        $account        = $this->account;
        $vendor         = $this->vendor;
        $user           = $this->user;
        $contactId      = $this->contact_id;
        $isSystem       = $this->is_system;

        if($vendor) {
            $route = $vendor->getRoute();

            $data = [
                'vendor' => link_to($route, $vendor->getDisplayName()),
                'user' => $isSystem ? '<i>' . trans('texts.system') . '</i>' : $user->getDisplayName(),
                'vendorcontact' => $contactId ? $vendor->getDisplayName() : $user->getDisplayName(),
            ];
        } else {
            return trans("texts.invalid_activity");
        }
        return trans("texts.activity_{$activityTypeId}", $data);
    }
}
