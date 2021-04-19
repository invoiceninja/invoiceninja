<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

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

    public function getLink() :string
    {
        $entity_type = Str::snake(class_basename($this->entityType()));

        $domain = isset($this->company->portal_domain) ?: $this->company->domain();

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

        $domain = isset($this->company->portal_domain) ?: $this->company->domain();

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
