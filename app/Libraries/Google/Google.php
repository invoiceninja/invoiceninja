<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Libraries\Google;

use Google_Client;

/**
 * Class Google.
 */
class Google
{
    public $client;

    public function __construct()
    {
        $this->client = new Google_Client();
    }

    public function init()
    {
        $this->client->setClientId(config('ninja.auth.google.client_id'));
        $this->client->setClientSecret(config('ninja.auth.google.client_secret'));

        return $this;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function checkAccessToken()
    {
    }

    public function refreshToken($user)
    {
        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($user->oauth_user_refresh_token);

            $access_token = $this->client->getAccessToken();

            $user->oauth_user_token = $access_token;

            $user->save();
        }

        return $this;
    }
}
