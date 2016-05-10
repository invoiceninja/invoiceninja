<?php namespace App\Models;

use Utils;
use Carbon;
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

    // If we're getting the link for PhantomJS to generate the PDF
    // we need to make sure it's served from our site
    public function getLink($type = 'view', $forceOnsite = false)
    {
        if (!$this->account) {
            $this->load('account');
        }

        $url = SITE_URL;
        $iframe_url = $this->account->iframe_url;
        
        if ($this->account->hasFeature(FEATURE_CUSTOM_URL)) {
            if ($iframe_url && !$forceOnsite) {
                return "{$iframe_url}?{$this->invitation_key}";
            } elseif ($this->account->subdomain) {
                $url = Utils::replaceSubdomain($url, $this->account->subdomain);
            }
        }
        
        return "{$url}/{$type}/{$this->invitation_key}";
    }

    public function getStatus()
    {
        $hasValue = false;
        $parts = [];
        $statuses = $this->message_id ? ['sent', 'opened', 'viewed'] : ['sent', 'viewed'];

        foreach ($statuses as $status) {
            $field = "{$status}_date";
            $date = '';
            if ($this->$field && $this->field != '0000-00-00 00:00:00') {
                $date = Utils::dateToString($this->$field);
                $hasValue = true;
            }
            $parts[] = trans('texts.invitation_status.' . $status) . ': ' . $date;
        }

        return $hasValue ? implode($parts, '<br/>') : false;
    }

    public function getName()
    {
        return $this->invitation_key;
    }

    public function markSent($messageId = null)
    {
        $this->message_id = $messageId;
        $this->email_error = null;
        $this->sent_date = Carbon::now()->toDateTimeString();
        $this->save();
    }

    public function markViewed()
    {
        $invoice = $this->invoice;
        $client = $invoice->client;

        $this->viewed_date = Carbon::now()->toDateTimeString();
        $this->save();

        $invoice->markViewed();
        $client->markLoggedIn();
    }
}
