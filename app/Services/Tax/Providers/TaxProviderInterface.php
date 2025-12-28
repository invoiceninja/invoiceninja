<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Tax\Providers;

interface TaxProviderInterface
{
    public function run();

    public function setApiCredentials(mixed $credentials);

}
