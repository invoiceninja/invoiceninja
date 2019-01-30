<?php namespace App\Ninja\OAuth\Providers;

class Google implements ProviderInterface
{

    public function getTokenResponse($token)
    {

        $client = new \Google_Client();
        return $client->verifyIdToken($token);
    }

    public function harvestEmail($payload)
    {
        return $payload['email'];
    }

    public function harvestSubField($payload)
    {
        return $payload['sub']; // user ID
    }
}
