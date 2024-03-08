<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\Utils\Ninja;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
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
    public function getStatus(): string
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
            /**@var \App\Models\Company $company */
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        return $domain.'/client/pay/'.$this->key;
    }

    public function getPaymentQrCode()
    {
        return htmlentities(
            sprintf('<div>%s</div>', $this->getPaymentQrCodeRaw())
        );
    }

    public function getPaymentQrCodeRaw()
    {

        $renderer = new ImageRenderer(
            new RendererStyle(150, margin: 0),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        $qr = $writer->writeString($this->getPaymentLink(), 'utf-8');

        return $qr;

    }

    public function getUnsubscribeLink()
    {
        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = strlen($this->company->portal_domain ?? '') > 5 ? $this->company->portal_domain : config('ninja.app_url');
        }

        $entity_type = Str::snake(class_basename($this->entityType()));

        return $domain.'/client/unsubscribe/'.$entity_type.'/'.$this->key;
    }

    public function getLink(): string
    {
        $entity_type = Str::snake(class_basename($this->entityType()));

        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = strlen($this->company->portal_domain ?? '') > 5 ? $this->company->portal_domain : config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':

                // if(Ninja::isHosted())
                //     return 'https://router.invoiceninja.com/route/'.encrypt($domain.'/client/'.$entity_type.'/'.$this->key);
                // else
                return $domain.'/client/'.$entity_type.'/'.$this->key;
                break;
            case 'iframe':
                return $domain.'/client/'.$entity_type.'/'.$this->key;
                break;
            case 'domain':
                return $domain.'/client/'.$entity_type.'/'.$this->key;
                break;

            default:
                return '';
                break;
        }
    }

    public function getPortalLink(): string
    {
        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = strlen($this->company->portal_domain ?? '') > 5 ? $this->company->portal_domain : config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain.'/client/';
                break;
            case 'iframe':
                return $domain.'/client/';
                break;
            case 'domain':
                return $domain.'/client/';
                break;

            default:
                return '';
                break;
        }
    }

    public function getAdminLink($use_react_link = false): string
    {
        return $use_react_link ? $this->getReactLink() : $this->getLink().'?silent=true';
    }

    private function getReactLink(): string
    {
        $entity_type = Str::snake(class_basename($this->entityType()));

        return config('ninja.react_url')."/#/{$entity_type}s/{$this->{$entity_type}->hashed_id}/edit";
    }
}
