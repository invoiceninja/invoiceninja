<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Invitation extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    public function contact()
    {
        return $this->belongsTo('App\Models\Contact')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function getLink()
    {
        $this->load('account');
        $url = SITE_URL;

        if ($this->account->subdomain) {
            $url = str_replace(['://www', '://'], "://{$this->account->subdomain}.", $url);
        }

        return "{$url}/view/{$this->invitation_key}";
    }
}
