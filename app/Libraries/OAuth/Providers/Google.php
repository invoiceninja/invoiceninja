<?php

namespace App\Libraries\OAuth\Providers;

use Google_Client;

class Google implements ProviderInterface
{
    public function getTokenResponse($token)
    {
        $client = new Google_Client();

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

    public function harvestName($payload)
    {
        return $payload['name'];
    }

    public function harvestUser($access_token)
    {
        $client = new Google_Client();
        $client->setClientId(config('ninja.auth.google.client_id'));
        $client->setClientSecret(config('ninja.auth.google.client_secret'));
        $client->setAccessToken($access_token);

        $oauth2 = new \Google_Service_Oauth2($client);

        try {
            $userInfo = $oauth2->userinfo->get();
        } catch (\Exception $e) {
            return false;
        }

        return [
            'email' => $userInfo['email'],
            'sub' => $userInfo['id'],
            'name' => $userInfo['name'],
        ];

    }
}
