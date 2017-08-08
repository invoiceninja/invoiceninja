<?php namespace App\Ninja\OAuth\Providers;

class Google implements ProviderInterface
{

    public function getTokenResponse($token)
    {

        $client = new \Google_Client(['client_id' => env('GOOGLE_CLIENT_ID','')]);
        $payload = $client->verifyIdToken($token);
        if ($payload)
            return $this->harvestSubField($payload);
        else
            return null;
    }

    public function harvestEmail($payload)
    {
        return $payload['email'];
    }

    private function harvestSubField($payload)
    {
        $data = $payload->getAttributes();
        return $data['payload']['sub']; // user ID
    }
}
