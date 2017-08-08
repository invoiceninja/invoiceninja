<?php

namespace App\Models;

use Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Utils;
use App\Models\LookupInvitation;

/**
 * Class Invitation.
 */
class Invitation extends EntityModel
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_INVITATION;
    }

    /**
     * @return mixed
     */
    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo('App\Models\Contact')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    // If we're getting the link for PhantomJS to generate the PDF
    // we need to make sure it's served from our site

    /**
     * @param string $type
     * @param bool   $forceOnsite
     *
     * @return string
     */
    public function getLink($type = 'view', $forceOnsite = false, $forcePlain = false)
    {
        if (! $this->account) {
            $this->load('account');
        }

        $account = $this->account;
        $iframe_url = $account->iframe_url;
        $url = trim(SITE_URL, '/');

        if (env('REQUIRE_HTTPS')) {
            $url = str_replace('http://', 'https://', $url);
        }

        if ($account->hasFeature(FEATURE_CUSTOM_URL)) {
            if (Utils::isNinjaProd()) {
                $url = $account->present()->clientPortalLink();
            }

            if ($iframe_url && ! $forceOnsite) {
                return "{$iframe_url}?{$this->invitation_key}";
            } elseif ($this->account->subdomain && ! $forcePlain) {
                $url = Utils::replaceSubdomain($url, $account->subdomain);
            }
        }

        return "{$url}/{$type}/{$this->invitation_key}";
    }

    /**
     * @return bool|string
     */
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
                $parts[] = trans('texts.invitation_status_' . $status) . ': ' . $date;
            }
        }

        return $hasValue ? implode($parts, '<br/>') : false;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->invitation_key;
    }

    /**
     * @param null $messageId
     */
    public function markSent($messageId = null)
    {
        $this->message_id = $messageId;
        $this->email_error = null;
        $this->sent_date = Carbon::now()->toDateTimeString();
        $this->save();
    }

    public function isSent()
    {
        return $this->sent_date && $this->sent_date != '0000-00-00 00:00:00';
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

    public function signatureDiv()
    {
        if (! $this->signature_base64) {
            return false;
        }

        return sprintf('<img src="data:image/svg+xml;base64,%s"></img><p/>%s: %s', $this->signature_base64, trans('texts.signed'), Utils::fromSqlDateTime($this->signature_date));
    }
}

Invitation::creating(function ($invitation)
{
    LookupInvitation::createNew($invitation->account->account_key, [
        'invitation_key' => $invitation->invitation_key,
    ]);
});

Invitation::updating(function ($invitation) {
    $dirty = $invitation->getDirty();
    if (array_key_exists('message_id', $dirty)) {
        LookupInvitation::updateInvitation($invitation->account->account_key, $invitation);
    }
});

Invitation::deleted(function ($invitation)
{
    if ($invitation->forceDeleting) {
        LookupInvitation::deleteWhere([
            'invitation_key' => $invitation->invitation_key,
        ]);
    }
});
