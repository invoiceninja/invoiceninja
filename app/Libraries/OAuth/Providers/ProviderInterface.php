<?php

namespace App\Libraries\OAuth\Providers;

interface ProviderInterface
{
    public function getTokenResponse($token);

    public function harvestEmail($response);

    public function harvestName($response);
}
