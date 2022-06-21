<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Libraries\OAuth;

use App\Libraries\MultiDB;
use App\Libraries\OAuth\Providers\Google;
use Laravel\Socialite\Facades\Socialite;

/**
 * Class OAuth.
 */
class OAuth
{
    /**
     * Socialite Providers.
     */
    const SOCIAL_GOOGLE = 1;

    const SOCIAL_FACEBOOK = 2;

    const SOCIAL_GITHUB = 3;

    const SOCIAL_LINKEDIN = 4;

    const SOCIAL_TWITTER = 5;

    const SOCIAL_BITBUCKET = 6;

    const SOCIAL_MICROSOFT = 7;

    const SOCIAL_APPLE = 8;

    /**
     * @param Socialite $user
     * @return bool|\App\Models\User|\App\Libraries\App\Models\User|null
     */
    public static function handleAuth($socialite_user, $provider)
    {
        /** 1. Ensure user arrives on the correct provider **/
        $query = [
            'oauth_user_id' => $socialite_user->getId(),
            'oauth_provider_id' => $provider,
        ];

        if ($user = MultiDB::hasUser($query)) {
            return $user;
        } else {
            return false;
        }
    }

    /* Splits a socialite user name into first and last names */
    public static function splitName($name)
    {
        $name = trim($name);
        $last_name = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
        $first_name = trim(preg_replace('#'.preg_quote($last_name, '/').'#', '', $name));

        return [$first_name, $last_name];
    }

    public static function providerToString(int $social_provider): string
    {
        switch ($social_provider) {
            case SOCIAL_GOOGLE:
                return 'google';
            case SOCIAL_FACEBOOK:
                return 'facebook';
            case SOCIAL_GITHUB:
                return 'github';
            case SOCIAL_LINKEDIN:
                return 'linkedin';
            case SOCIAL_TWITTER:
                return 'twitter';
            case SOCIAL_BITBUCKET:
                return 'bitbucket';
            case SOCIAL_MICROSOFT:
                return 'microsoft';
            case SOCIAL_APPLE:
                return 'apple';
        }
    }

    public static function providerToInt(string $social_provider): int
    {
        switch ($social_provider) {
            case 'google':
                return SOCIAL_GOOGLE;
            case 'facebook':
                return SOCIAL_FACEBOOK;
            case 'github':
                return SOCIAL_GITHUB;
            case 'linkedin':
                return SOCIAL_LINKEDIN;
            case 'twitter':
                return SOCIAL_TWITTER;
            case 'bitbucket':
                return SOCIAL_BITBUCKET;
            case 'microsoft':
                return SOCIAL_MICROSOFT;
            case 'apple':
                return SOCIAL_APPLE;
        }
    }

    public function getProvider($provider)
    {
        switch ($provider) {
            case 'google':
                $this->provider_instance = new Google();
                $this->provider_id = self::SOCIAL_GOOGLE;

                return $this;
            default:
                return null;
                break;
        }
    }

    public function getTokenResponse($token)
    {
        $user = false;

        $payload = $this->provider_instance->getTokenResponse($token);

        $oauth_user_id = $this->provider_instance->harvestSubField($payload);

        $data = [
            'oauth_user_id' => $oauth_user_id,
            'oauth_provider_id' => $this->provider_id,
        ];

        if ($this->provider_instance) {
            $user = MultiDB::hasUser($data);
        }

        return $user;
    }
}
