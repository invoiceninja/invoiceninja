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

    }
}