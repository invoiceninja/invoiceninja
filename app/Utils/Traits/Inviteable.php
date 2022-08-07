<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\Utils\Ninja;
use Illuminate\Support\Str;

/**
 * Class Inviteable.
 */
trait Inviteable
{
    /**
     * Gets the status.
     *
     * @return     string  The status.
     */
    public function getStatus() :string
    {
        $status = '';

        if (isset($this->sent_date)) {
            $status = ctrans('texts.invitation_status_sent');
        }

        if (isset($this->opened_date)) {
            $status = ctrans('texts.invitation_status_opened');
        }

        if (isset($this->viewed_date)) {
            $status = ctrans('texts.invitation_status_viewed');
        }

        return $status;
    }

    public function getPaymentLink()
    {
        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        return $domain.'/client/pay/'.$this->key;
    }

    public function getUnsubscribeLink()
    {
        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        $entity_type = Str::snake(class_basename($this->entityType()));

        return $domain.'/client/unsubscribe/'.$entity_type.'/'.$this->key;
    }

    public function getLink() :string
    {
        $entity_type = Str::snake(class_basename($this->entityType()));

        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain.'/client/'.$entity_type.'/'.$this->key;
                break;
            case 'iframe':
                return $domain.'/client/'.$entity_type.'/'.$this->key;
                //return $domain . $entity_type .'/'. $this->contact->client->client_hash .'/'. $this->key;
                break;
            case 'domain':
                return $domain.'/client/'.$entity_type.'/'.$this->key;
                break;

            default:
                return '';
                break;
        }
    }

    public function getPortalLink() :string
    {
        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain.'/client/';
                break;
            case 'iframe':
                return $domain.'/client/';
                //return $domain . $entity_type .'/'. $this->contact->client->client_hash .'/'. $this->key;
                break;
            case 'domain':
                return $domain.'/client/';
                break;

            default:
                return '';
                break;
        }
    }

    public function getAdminLink() :string
    {
        return $this->getLink().'?silent=true';
    }
}
