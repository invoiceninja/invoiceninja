<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

/**
 * Class Inviteable
 * @package App\Utils\Traits
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
        $entity_type = strtolower(class_basename($this->entityType()));

        //$this->with('company','contact',$this->entity_type);
        //$this->with('company');

        $domain = isset($this->company->portal_domain) ?: $this->company->domain();

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain .'client/'. $entity_type .'/'. $this->key;
                break;
            case 'iframe':
                return $domain .'client/'. $entity_type .'/'. $this->key;
                //return $domain . $entity_type .'/'. $this->contact->client->client_hash .'/'. $this->key;
                break;
            case 'domain':
                return $domain .'client/'. $entity_type .'/'. $this->key;
                break;

        }
    }

    public function getAdminLink() :string
    {
        return $this->getLink(). '?silent=true';
    }
}
