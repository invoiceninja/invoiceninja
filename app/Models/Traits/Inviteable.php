<?php

namespace App\Models\Traits;

use Carbon;
use Utils;

/**
 * Class SendsEmails.
 */
trait Inviteable
{
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

        if ($this->proposal_id) {
            $type = 'proposal';
        }

        $account = $this->account;
        $iframe_url = $account->iframe_url;
        $url = trim(SITE_URL, '/');

        if (env('REQUIRE_HTTPS')) {
            $url = str_replace('http://', 'https://', $url);
        }

        if ($account->hasFeature(FEATURE_CUSTOM_URL)) {
            if (Utils::isNinjaProd() && ! Utils::isReseller()) {
                $url = $account->present()->clientPortalLink();
            }

            if ($iframe_url && ! $forceOnsite) {
                if ($account->is_custom_domain) {
                    $url = $iframe_url;
                } else {
                    return "{$iframe_url}?{$this->invitation_key}/{$type}";
                }
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
        $this->viewed_date = Carbon::now()->toDateTimeString();
        $this->save();

        if ($this->invoice) {
            $invoice = $this->invoice;
            $client = $invoice->client;

            $invoice->markViewed();
            $client->markLoggedIn();
        }
    }
}
