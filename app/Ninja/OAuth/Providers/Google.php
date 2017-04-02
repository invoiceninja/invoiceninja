<?php namespace App\Ninja\OAuth\Providers;

class Google implements ProviderInterface
{

    public function getTokenResponse($token)
    {

        $client = new \Google_Client(['client_id' => env('GOOGLE_CLIENT_ID','')]);
        $payload = $client->verifyIdToken($token);
        if ($payload)
            return $this->harvestEmail($payload);
        else
            return null;
    }

    public function harvestEmail($payload)
    {
        return $payload['email'];
    }


}
