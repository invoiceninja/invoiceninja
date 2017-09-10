<?php namespace App\Ninja\OAuth\Providers;

class Google implements ProviderInterface
{

    public function getTokenResponse($token)
    {

        $client = new \Google_Client(['client_id' => env('GOOGLE_CLIENT_ID','')]);
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
