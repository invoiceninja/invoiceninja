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
namespace App\Libraries\OAuth\Providers;

interface ProviderInterface
{
    public function getTokenResponse($token);

    public function harvestEmail($response);

    public function harvestName($response);
}
