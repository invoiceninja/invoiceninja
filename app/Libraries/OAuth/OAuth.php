<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Libraries\OAuth;

use App\Libraries\MultiDB;
use App\Libraries\OAuth\Providers\Google;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
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
        $first_name = trim(preg_replace('#' . preg_quote($last_name, '/') . '#', '', $name));

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

    public static function appleUserFromToken(string $token)
    {
        $client_id = self::appleClientId($token);

        /** @var \SocialiteProviders\Apple\Provider $driver */
        $driver = Socialite::driver('apple');
        $driver->setConfig(new \SocialiteProviders\Manager\Config($client_id, config('services.apple.client_secret'), config('services.apple.redirect')));

        return $driver->userFromToken($token);
    }

    /**
     * Revokes the user's Sign in with Apple tokens, as Apple requires when an account is deleted.
     *
     * The authorization code is exchanged for a refresh token, which is revoked only when the
     * identity token returned with it is for the Apple user ID the deleted user signed in with.
     * Best effort: a failure is logged, never thrown.
     */
    public static function revokeAppleToken(string $authorization_code, string $id_token, string $apple_user_id, ?string $redirect_uri = null): bool
    {
        if (!config('services.apple.revoke.team_id') || !config('services.apple.revoke.key_id') || !config('services.apple.revoke.private_key')) {
            nlog("OAuth::revokeAppleToken - the Sign in with Apple key is not configured, nothing was revoked");
            return false;
        }

        try {
            $client_id = self::appleClientId($id_token);
            $client_secret = self::appleClientSecret($client_id);

            $response = Http::asForm()->timeout(10)->post('https://appleid.apple.com/auth/token', array_filter([
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $authorization_code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirect_uri,
            ]));

            if ($response->failed() || !$response->json('refresh_token')) {
                nlog("OAuth::revokeAppleToken - could not exchange the authorization code: " . $response->status() . " " . $response->json('error'));
                return false;
            }

            if (!$response->json('id_token') || self::appleUserFromToken($response->json('id_token'))->getId() !== $apple_user_id) {
                nlog("OAuth::revokeAppleToken - Apple's identity token is missing or for another user, nothing was revoked");
                return false;
            }

            $response = Http::asForm()->timeout(10)->post('https://appleid.apple.com/auth/revoke', [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'token' => $response->json('refresh_token'),
                'token_type_hint' => 'refresh_token',
            ]);

            if ($response->failed()) {
                nlog("OAuth::revokeAppleToken - could not revoke the token: " . $response->status() . " " . $response->json('error'));
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            nlog("OAuth::revokeAppleToken - could not revoke the token: " . $e->getMessage());
        }

        return false;
    }

    /* iOS/macOS tokens are issued for the bundle ID, web/Android tokens for the service ID */
    private static function appleClientId(string $token): string
    {
        $payload = json_decode(base64_decode(strtr(explode('.', $token)[1] ?? '', '-_', '+/')), true);

        return in_array($payload['aud'] ?? null, config('services.apple.client_ids'), true)
            ? $payload['aud']
            : config('services.apple.client_id');
    }

    /* Apple's client secret is a JWT signed with the Sign in with Apple key, for the client ID the code was issued to */
    private static function appleClientSecret(string $client_id): string
    {
        $issue_time = time();

        $payload = [
            'iss' => config('services.apple.revoke.team_id'),
            'iat' => $issue_time,
            'exp' => $issue_time + 60 * 5,
            'aud' => 'https://appleid.apple.com',
            'sub' => $client_id,
        ];

        return JWT::encode($payload, config('services.apple.revoke.private_key'), 'ES256', config('services.apple.revoke.key_id'));
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
