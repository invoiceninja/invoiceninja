<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Libraries\OAuth\Providers\Oidc;

use SocialiteProviders\Manager\SocialiteWasCalled;

/**
 * Listener that registers the generic OIDC driver with Laravel Socialite.
 *
 * Wired to the SocialiteWasCalled event in EventServiceProvider.
 */
class OidcExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('oidc', Provider::class);
    }
}
