<?php

namespace App\Libraries;

use Laravel\Socialite\Facades\Socialite;

/**
 * Class OAuth
 * @package App\Libraries
 */
class OAuth
{

    /**
     * Socialite Providers
     */
    const SOCIAL_GOOGLE = 1;
    const SOCIAL_FACEBOOK = 2;
    const SOCIAL_GITHUB = 3;
    const SOCIAL_LINKEDIN = 4;
    const SOCIAL_TWITTER = 5;
    const SOCIAL_BITBUCKET = 6;

    /**
     * @param Socialite $user
     */

    public static function handleAuth($user)
    {
        if(MultiDB::checkUserEmailExists($user->getEmail())) //if email is in the system this is an existing user -> check they are arriving on the correct provider
        {

        }
    }

    public static function toString($social_provider)
    {
        switch ($social_provider)
        {
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
        }
    }
}