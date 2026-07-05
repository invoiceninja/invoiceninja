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

namespace App\Libraries\OAuth\Providers\Oidc;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;

/**
 * Generic OpenID Connect (OIDC) Socialite provider.
 *
 * Endpoints are discovered from the `.well-known/openid-configuration`
 * document exposed by any OIDC-compliant identity provider (Authentik,
 * Keycloak, Zitadel, Okta, Auth0, ...). The discovered metadata is
 * cached for one hour to avoid a network round-trip on every login.
 *
 * Configuration lives under `config/services.php` -> `oidc`:
 *   - well_known   (required) full URL to the discovery document
 *   - client_id    (required)
 *   - client_secret (required)
 *   - redirect     (required) callback URL, e.g. https://app.example.com/auth/oidc
 *   - scopes       (optional) space-separated list, default "openid profile email"
 */
class Provider extends AbstractProvider implements ProviderInterface
{
    public const IDENTIFIER = 'OIDC';

    /**
     * Extra config keys the SocialiteProviders/Manager should hydrate onto
     * the driver instance. Required by the manager convention even when we
     * pull everything else from the .well-known discovery document.
     */
    public static function additionalConfigKeys(): array
    {
        return ['well_known', 'scopes'];
    }

    /**
     * Cached OIDC discovery document.
     *
     * @var array<string,mixed>|null
     */
    protected ?array $discovery = null;

    /**
     * {@inheritdoc}
     */
    protected $scopes = ['openid', 'profile', 'email'];

    /**
     * {@inheritdoc}
     */
    protected $scopeSeparator = ' ';

    /**
     * Return the discovered OIDC metadata, fetching + caching on first use.
     *
     * @return array<string,mixed>
     */
    protected function discovery(): array
    {
        if ($this->discovery !== null) {
            return $this->discovery;
        }

        $wellKnown = (string) config('services.oidc.well_known');

        if ($wellKnown === '') {
            throw new \RuntimeException('OIDC discovery URL (OIDC_WELL_KNOWN) is not configured.');
        }

        $this->discovery = Cache::remember(
            'oidc.discovery.' . sha1($wellKnown),
            now()->addHour(),
            function () use ($wellKnown): array {
                $response = $this->getHttpClient()->get($wellKnown, [
                    RequestOptions::HEADERS => ['Accept' => 'application/json'],
                    RequestOptions::TIMEOUT => 10,
                ]);

                $payload = json_decode((string) $response->getBody(), true);

                if (!is_array($payload) || !isset($payload['authorization_endpoint'], $payload['token_endpoint'])) {
                    throw new \RuntimeException('OIDC discovery document is malformed at ' . $wellKnown);
                }

                return $payload;
            }
        );

        return $this->discovery;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->discovery()['authorization_endpoint'], $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return $this->discovery()['token_endpoint'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token): array
    {
        $userinfoEndpoint = $this->discovery()['userinfo_endpoint'] ?? null;

        if (!$userinfoEndpoint) {
            throw new \RuntimeException('OIDC provider does not advertise a userinfo_endpoint.');
        }

        $response = $this->getHttpClient()->get($userinfoEndpoint, [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
            RequestOptions::TIMEOUT => 10,
        ]);

        return (array) json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user): User
    {
        $given = Arr::get($user, 'given_name', '');
        $family = Arr::get($user, 'family_name', '');

        $displayName = Arr::get($user, 'name')
            ?: trim($given . ' ' . $family)
            ?: (string) Arr::get($user, 'preferred_username', '');

        return (new User())->setRaw($user)->map([
            'id'         => Arr::get($user, 'sub'),
            'nickname'   => Arr::get($user, 'preferred_username'),
            'name'       => $displayName,
            'first_name' => $given,
            'last_name'  => $family,
            'email'      => Arr::get($user, 'email'),
            'avatar'     => Arr::get($user, 'picture'),
        ]);
    }
}
