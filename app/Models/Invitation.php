<?php namespace App\Models;

use Utils;
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
        if (!$this->account) {
            $this->load('account');
        }

        $url = SITE_URL;
        $iframe_url = $this->account->iframe_url;
                
        if ($iframe_url) {
            return "{$iframe_url}/?{$this->invitation_key}";
        } else if ($this->account->subdomain) {
            $url = Utils::replaceSubdomain($url, $this->subdomain);
        }

        return "{$url}/view/{$this->invitation_key}";
    }

    public function getName()
    {
        return $this->invitation_key;
    }
}
