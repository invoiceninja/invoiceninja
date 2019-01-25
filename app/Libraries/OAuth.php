<?php

namespace App\Libraries;

use App\Models\User;
use Illuminate\Support\Facades\Session;
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

    public static function handleAuth(object $user, string $provider) : ?User
    {
        /** 1. Ensure user arrives on the correct provider **/

        $query = [
            'oauth_user_id' =>$user->getId(),
            'oauth_provider_id'=>$provider
        ];

        if($user = MultiDB::hasUser($query))
        {
            return $user;
        }

        /** 2. If email exists, then they already have an account did they select the wrong provider? redirect to a guest error screen */

        if(MultiDB::checkUserEmailExists($user->getEmail()))
        {
            Session::flash('error', 'User exists in system, but not with this authentication method'); //todo add translations
            return view('auth.login');
        }

        /*

            Session::flash('error', 'User does not exist'); //todo add translations
            return view('auth.login');
        */
       
        /** 3. We will not handle automagically creating a new account here. */


    }

    public static function providerToString(int $social_provider) : string
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

    public static function providerToInt(string $social_provider) : int
    {
        switch ($social_provider)
        {
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
        }
    }
}