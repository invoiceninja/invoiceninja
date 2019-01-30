<?php namespace App\Ninja\OAuth\Providers;

interface ProviderInterface
{
    public function getTokenResponse($token);

    public function harvestEmail($response);

}
