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

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
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
     * {@inheritdoc}
     *
     * Enable PKCE on the authorization-code exchange. Any OIDC-conformant
     * IdP either requires or tolerates PKCE, and enabling it hardens the
     * browser flow against authorization-code interception even when the
     * client secret is present.
     */
    public function __construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle = [])
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);

        $this->enablePKCE();
    }

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
     *
     * Overridden to verify the `id_token` returned alongside the access
     * token — signature via the IdP's JWKS, plus the OIDC-mandated
     * `iss` / `aud` / `exp` claims — before we trust anything the
     * userinfo endpoint tells us. The `sub` from the id_token is also
     * cross-checked against the userinfo response to defend against
     * token-substitution attacks.
     */
    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new \Laravel\Socialite\Two\InvalidStateException();
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $idToken = Arr::get($response, 'id_token');

        if (!$idToken) {
            throw new \RuntimeException('OIDC token response did not include an id_token.');
        }

        $claims = $this->verifyIdToken($idToken);

        $accessToken = Arr::get($response, 'access_token');
        $userinfo = $this->getUserByToken($accessToken);

        // Prefer verified claims from the id_token when the userinfo
        // response is missing the subject, and refuse the login when the
        // two disagree.
        $userinfo['sub'] = $userinfo['sub'] ?? $claims['sub'] ?? null;

        if (!$userinfo['sub'] || $userinfo['sub'] !== ($claims['sub'] ?? null)) {
            throw new \RuntimeException('OIDC userinfo subject does not match id_token subject.');
        }

        $user = $this->mapUserToObject($userinfo);

        return $user->setToken($accessToken)
            ->setRefreshToken(Arr::get($response, 'refresh_token'))
            ->setExpiresIn(Arr::get($response, 'expires_in'))
            ->setApprovedScopes(explode($this->scopeSeparator, (string) Arr::get($response, 'scope', '')));
    }

    /**
     * Verify the id_token signature and required OIDC claims.
     *
     * @return array<string,mixed> the decoded claim set
     */
    protected function verifyIdToken(string $idToken): array
    {
        $discovery = $this->discovery();
        $issuer = $discovery['issuer'] ?? null;
        $jwksUri = $discovery['jwks_uri'] ?? null;

        if (!$issuer || !$jwksUri) {
            throw new \RuntimeException('OIDC discovery document is missing issuer or jwks_uri.');
        }

        // Some IdPs publish JWKS entries without an `alg` field; without a
        // default the parser rejects the whole key set. Read the alg from
        // the id_token header and use it as the default so the parse
        // succeeds for both alg-tagged and untagged keys.
        $tokenAlg = $this->idTokenAlg($idToken);
        $keys = JWK::parseKeySet($this->jwks($jwksUri), $tokenAlg);

        try {
            $claims = (array) JWT::decode($idToken, $keys);
        } catch (\Throwable $e) {
            throw new \RuntimeException('OIDC id_token signature verification failed: ' . $e->getMessage(), 0, $e);
        }

        if (($claims['iss'] ?? null) !== $issuer) {
            throw new \RuntimeException('OIDC id_token issuer mismatch.');
        }

        $aud = $claims['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];

        if (!in_array((string) config('services.oidc.client_id'), $audiences, true)) {
            throw new \RuntimeException('OIDC id_token audience does not include this client.');
        }

        if (isset($claims['azp']) && $claims['azp'] !== (string) config('services.oidc.client_id')) {
            throw new \RuntimeException('OIDC id_token authorized-party (azp) mismatch.');
        }

        return $claims;
    }

    /**
     * Read the `alg` header of a JWS-compact serialised id_token.
     *
     * Falls back to RS256 (the OIDC-registered default) when the header is
     * missing an alg, so callers always get a usable value to hand to the
     * JWKS parser.
     */
    protected function idTokenAlg(string $idToken): string
    {
        $segments = explode('.', $idToken);
        if (count($segments) < 2) {
            return 'RS256';
        }

        $header = json_decode(
            (string) base64_decode(strtr($segments[0], '-_', '+/'), true),
            true
        );

        $alg = is_array($header) ? ($header['alg'] ?? null) : null;

        return is_string($alg) && $alg !== '' ? $alg : 'RS256';
    }

    /**
     * Fetch and cache the IdP's JWKS document.
     *
     * @return array<string,mixed>
     */
    protected function jwks(string $jwksUri): array
    {
        return Cache::remember(
            'oidc.jwks.' . sha1($jwksUri),
            now()->addHour(),
            function () use ($jwksUri): array {
                $response = $this->getHttpClient()->get($jwksUri, [
                    RequestOptions::HEADERS => ['Accept' => 'application/json'],
                    RequestOptions::TIMEOUT => 10,
                ]);

                $payload = json_decode((string) $response->getBody(), true);

                if (!is_array($payload) || !isset($payload['keys'])) {
                    throw new \RuntimeException('OIDC JWKS document is malformed at ' . $jwksUri);
                }

                return $payload;
            }
        );
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
