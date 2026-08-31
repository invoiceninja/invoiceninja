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
     * Asymmetric-signature algorithms we are willing to verify an
     * id_token with. HMAC (`HS*`) and `none` are deliberately absent —
     * JWKS is public-key material, so any `HS*` alg against a JWKS key
     * would be an alg-confusion attack (the public key bytes become
     * the HMAC secret). Used only when the IdP's discovery document
     * omits `id_token_signing_alg_values_supported`.
     *
     * Scoped to what firebase/php-jwt v7.x actually implements: RS*, PS256,
     * ES256/ES384, and EdDSA. PS384/PS512/ES512 are intentionally excluded
     * because JWT::decode would throw "Algorithm not supported" at runtime.
     *
     * @var string[]
     */
    protected const ID_TOKEN_ALG_ALLOWLIST = [
        'RS256', 'RS384', 'RS512',
        'PS256',
        'ES256', 'ES384',
        'EdDSA',
    ];

    /**
     * Map from a signing alg to the JWK `kty` value we require on the
     * matching public key. Enforced so a JWK of the wrong shape cannot
     * be reinterpreted as material for a different algorithm family
     * (again: alg-confusion defence). Kept in lock-step with the
     * allowlist above.
     *
     * @var array<string,string>
     */
    protected const ALG_KTY_MAP = [
        'RS256' => 'RSA', 'RS384' => 'RSA', 'RS512' => 'RSA',
        'PS256' => 'RSA',
        'ES256' => 'EC',  'ES384' => 'EC',
        'EdDSA' => 'OKP',
    ];

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

        // The header is read *only* to choose which JWKS entry to try
        // and which alg the caller claims — every field it reports has
        // to be re-validated against server-trusted metadata before we
        // decode, otherwise an attacker who controls the token can
        // steer us into an alg-confusion attack (e.g. HS256 against an
        // EC public key). See:
        // https://openid.net/specs/openid-connect-core-1_0.html#IDTokenValidation
        $header = $this->idTokenHeader($idToken);
        $alg = $header['alg'] ?? null;
        $kid = $header['kid'] ?? null;

        if (!is_string($alg) || $alg === '') {
            throw new \RuntimeException('OIDC id_token header is missing alg.');
        }

        // Hard allowlist. Rejects `HS*`, `none`, and anything else the
        // IdP has no business signing an id_token with.
        if (!array_key_exists($alg, self::ALG_KTY_MAP)) {
            throw new \RuntimeException('OIDC id_token uses an unsupported or unsafe alg: ' . $alg);
        }

        // OIDC Discovery §3 requires id_token_signing_alg_values_supported.
        // When the IdP publishes it we honour it exactly; otherwise fall
        // back to our own asymmetric-only allowlist rather than trusting
        // the token header.
        $supportedAlgs = $discovery['id_token_signing_alg_values_supported'] ?? self::ID_TOKEN_ALG_ALLOWLIST;

        if (!is_array($supportedAlgs) || !in_array($alg, $supportedAlgs, true)) {
            throw new \RuntimeException('OIDC id_token alg ' . $alg . ' is not in the IdP advertised signing algorithms.');
        }

        $jwks = $this->jwks($jwksUri);
        $jwk = $this->pickJwk(is_array($jwks['keys'] ?? null) ? $jwks['keys'] : [], is_string($kid) ? $kid : null, $alg);

        // The JWK's kty must match the alg family (RSA/EC/OKP). Without
        // this check a JWK of the wrong type could be coerced into
        // material for an alg it was never issued for.
        if (($jwk['kty'] ?? null) !== self::ALG_KTY_MAP[$alg]) {
            throw new \RuntimeException('OIDC JWK kty does not match id_token alg family.');
        }

        // Overwrite the JWK's own `alg` (if any) with our validated
        // value so JWK::parseKey cannot be nudged into a different
        // algorithm by a hostile JWKS document.
        $jwk['alg'] = $alg;

        try {
            $key = JWK::parseKey($jwk, $alg);
            $claims = (array) JWT::decode($idToken, $key);
        } catch (\Throwable $e) {
            throw new \RuntimeException('OIDC id_token signature verification failed: ' . $e->getMessage(), 0, $e);
        }

        // OIDC Core §2 requires both iat and exp. JWT::decode enforces exp
        // when present, but treats an absent claim as "no expiry" — which
        // would let a token with no lifetime be replayed forever. Reject
        // outright.
        if (!isset($claims['exp']) || !is_numeric($claims['exp'])) {
            throw new \RuntimeException('OIDC id_token is missing required exp claim.');
        }

        if (!isset($claims['iat']) || !is_numeric($claims['iat'])) {
            throw new \RuntimeException('OIDC id_token is missing required iat claim.');
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
     * Decode the JOSE header of a JWS-compact serialised id_token.
     *
     * Callers must treat every field returned here as attacker-controlled
     * until it is cross-checked against server-trusted metadata (JWKS,
     * discovery). This helper deliberately performs no validation of its
     * own — it just parses base64url + JSON.
     *
     * @return array<string,mixed>
     */
    protected function idTokenHeader(string $idToken): array
    {
        $segments = explode('.', $idToken);

        if (count($segments) < 2) {
            throw new \RuntimeException('OIDC id_token is not a JWS compact serialization.');
        }

        $header = json_decode(
            (string) base64_decode(strtr($segments[0], '-_', '+/'), true),
            true
        );

        if (!is_array($header)) {
            throw new \RuntimeException('OIDC id_token header is not valid JSON.');
        }

        return $header;
    }

    /**
     * Pick the JWKS entry to verify against, preferring an exact `kid`
     * match and otherwise the single key whose `kty` fits the alg.
     *
     * We fail loudly instead of returning "some key that might work" —
     * ambiguity in key selection is another vector for alg-confusion.
     *
     * @param array<int,array<string,mixed>> $jwks
     * @return array<string,mixed>
     */
    protected function pickJwk(array $jwks, ?string $kid, string $alg): array
    {
        $expectedKty = self::ALG_KTY_MAP[$alg] ?? null;

        if ($kid !== null && $kid !== '') {
            foreach ($jwks as $jwk) {
                if (is_array($jwk) && ($jwk['kid'] ?? null) === $kid) {
                    return $jwk;
                }
            }

            throw new \RuntimeException('OIDC id_token kid does not match any JWKS entry.');
        }

        $candidates = array_values(array_filter(
            $jwks,
            fn ($jwk) => is_array($jwk) && ($jwk['kty'] ?? null) === $expectedKty
        ));

        if (count($candidates) === 0) {
            throw new \RuntimeException('OIDC JWKS has no key compatible with id_token alg.');
        }

        if (count($candidates) > 1) {
            throw new \RuntimeException('OIDC id_token has no kid and JWKS is ambiguous.');
        }

        return $candidates[0];
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
