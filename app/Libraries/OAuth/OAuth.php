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
    public const SOCIAL_GOOGLE = 1;

    public const SOCIAL_FACEBOOK = 2;

    public const SOCIAL_GITHUB = 3;

    public const SOCIAL_LINKEDIN = 4;

    public const SOCIAL_TWITTER = 5;

    public const SOCIAL_BITBUCKET = 6;

    public const SOCIAL_MICROSOFT = 7;

    public const SOCIAL_APPLE = 8;

    public $provider_instance;

    public $provider_id;

    /**
     * @param \Laravel\Socialite\Facades\Socialite $socialite_user
     * @return bool | \App\Models\User | \App\Models\User | null
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
            case self::SOCIAL_GOOGLE:
                return 'google';
            case self::SOCIAL_FACEBOOK:
                return 'facebook';
            case self::SOCIAL_GITHUB:
                return 'github';
            case self::SOCIAL_LINKEDIN:
                return 'linkedin';
            case self::SOCIAL_TWITTER:
                return 'twitter';
            case self::SOCIAL_BITBUCKET:
                return 'bitbucket';
            case self::SOCIAL_MICROSOFT:
                return 'microsoft';
            case self::SOCIAL_APPLE:
                return 'apple';
            default:
                return 'google';
        }
    }

    public static function providerToInt(string $social_provider): int
    {
        switch ($social_provider) {
            case 'google':
                return self::SOCIAL_GOOGLE;
            case 'facebook':
                return self::SOCIAL_FACEBOOK;
            case 'github':
                return self::SOCIAL_GITHUB;
            case 'linkedin':
                return self::SOCIAL_LINKEDIN;
            case 'twitter':
                return self::SOCIAL_TWITTER;
            case 'bitbucket':
                return self::SOCIAL_BITBUCKET;
            case 'microsoft':
                return self::SOCIAL_MICROSOFT;
            case 'apple':
                return self::SOCIAL_APPLE;
            default:
                return self::SOCIAL_GOOGLE;
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
