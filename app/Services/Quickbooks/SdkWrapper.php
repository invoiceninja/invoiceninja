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

namespace App\Services\Quickbooks;

use App\DataMapper\QuickbooksSettings;
use Carbon\Carbon;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Exception\ServiceException;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;

class SdkWrapper
{
    public const MAXRESULTS = 1000;

    private const ACCESS_TOKEN_REFRESH_LEEWAY_SECONDS = 300;

    private const TOKEN_REFRESH_LOCK_SECONDS = 30;

    private const TOKEN_REFRESH_LOCK_WAIT_SECONDS = 10;

    private const RATE_LIMIT_MAX_WAIT_SECONDS = 90;

    private $entities = ['Customer','Invoice', 'Item', 'SalesReceipt', 'Vendor', 'Purchase', 'Payment'];

    private ?OAuth2AccessToken $token = null;

    private ?QuickbooksRateLimiter $rate_limiter = null;

    public function __construct(public DataService $sdk, private Company $company)
    {
        $this->init();
    }

    private function init(): self
    {
        // Only set access token if quickbooks settings exist and have valid token data
        // During reconnection flow, we may not have valid tokens yet
        if ($this->company->quickbooks
            && $this->company->quickbooks->accessTokenKey
            && !$this->company->quickbooks->requires_reconnect) {
            $this->setNinjaAccessToken($this->company->quickbooks);
        }

        return $this;

    }

    public function getAuthorizationUrl(): string
    {
        return $this->sdk->getOAuth2LoginHelper()->getAuthorizationCodeURL();
    }

    public function getState(): string
    {
        return $this->sdk->getOAuth2LoginHelper()->getState();
    }

    public function getRefreshToken(): string
    {
        return $this->accessToken()->getRefreshToken();
    }

    public function revokeAccessToken()
    {
        return $this->sdk->getOAuth2LoginHelper()->revokeToken($this->accessToken()->getAccessToken());
    }

    public function company()
    {
        return $this->execute(fn () => $this->sdk->getCompanyInfo());
    }

    public function getPreferences()
    {
        return $this->execute(fn () => $this->sdk->getCompanyPreferences());
    }
    /*
    accessTokenKey
    tokenType
    refresh_token
    accessTokenExpiresAt
    refreshTokenExpiresAt
    accessTokenValidationPeriod
    refreshTokenValidationPeriod
    clientID
    clientSecret
    realmID
    baseURL
    */
    public function accessTokenFromCode(string $code, string $realm): OAuth2AccessToken
    {
        $token = $this->sdk->getOAuth2LoginHelper()->exchangeAuthorizationCodeForToken($code, $realm);

        $this->setAccessToken($token);

        return $this->accessToken();
    }

    /**
     * Set Stored NinjaAccessToken
     *
     * @param  QuickbooksSettings $token_object
     * @return self
     */
    public function setNinjaAccessToken(QuickbooksSettings $token_object): self
    {
        $token = new OAuth2AccessToken(
            config('services.quickbooks.client_id'),
            config('services.quickbooks.client_secret'),
            $token_object->accessTokenKey,
            $token_object->refresh_token,
            3600,
            8726400
        );

        $token->setAccessTokenExpiresAt($token_object->accessTokenExpiresAt); //@phpstan-ignore-line
        $token->setRefreshTokenExpiresAt($token_object->refreshTokenExpiresAt); //@phpstan-ignore-line
        $token->setAccessTokenValidationPeriodInSeconds(3600);
        $token->setRefreshTokenValidationPeriodInSeconds(8726400);

        $this->setAccessToken($token);
        $this->preserveConnectionContext($token, $token_object);

        if ($token_object->accessTokenExpiresAt != 0 && $token_object->accessTokenExpiresAt < time()) {
            $this->refreshTokenLocked(true);
        }

        return $this;
    }


    public function refreshToken(string $refresh_token): self
    {
        $new_token = $this->sdk->getOAuth2LoginHelper()->refreshAccessTokenWithRefreshToken($refresh_token);

        if ($this->company->quickbooks) {
            $this->preserveConnectionContext($new_token, $this->company->quickbooks);
        }

        $this->setAccessToken($new_token);
        $this->sdk->updateOAuth2Token($this->accessToken());
        $this->saveOAuthToken($this->accessToken());

        return $this;
    }

    public function refreshTokenLocked(bool $force = false): self
    {
        if (! $this->company->quickbooks || $this->company->quickbooks->requires_reconnect) {
            return $this;
        }

        Cache::lock(
            "quickbooks-token-refresh:{$this->company->id}:{$this->company->db}",
            self::TOKEN_REFRESH_LOCK_SECONDS
        )->block(self::TOKEN_REFRESH_LOCK_WAIT_SECONDS, function () use ($force): void {
            $fresh_company = $this->company->fresh();

            if ($fresh_company) {
                $this->company = $fresh_company;
            }

            if (! $this->company->quickbooks || $this->company->quickbooks->requires_reconnect) {
                return;
            }

            if (! $force && ! $this->tokenNeedsRefresh()) {
                $this->setNinjaAccessToken($this->company->quickbooks);
                $this->sdk->updateOAuth2Token($this->accessToken());

                return;
            }

            if ($this->refreshTokenExpired()) {
                $this->markRequiresReconnect();

                throw new \RuntimeException('Quickbooks refresh token expired');
            }

            try {
                $this->refreshToken($this->company->quickbooks->refresh_token);
            } catch (\Throwable $e) {
                if ($this->isRefreshTokenFailure($e)) {
                    $this->markRequiresReconnect();
                }

                throw $e;
            }
        });

        return $this;
    }

    /**
     * SetsAccessToken
     *
     * @param  OAuth2AccessToken $token
     * @return self
     */
    public function setAccessToken(OAuth2AccessToken $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function accessToken(): OAuth2AccessToken
    {
        return $this->token;
    }

    public function saveOAuthToken(OAuth2AccessToken $token): void
    {
        $obj = $this->company->quickbooks ?? new QuickbooksSettings();
        $obj->accessTokenKey = $token->getAccessToken();
        $obj->refresh_token = $token->getRefreshToken();
        $obj->accessTokenExpiresAt = Carbon::createFromFormat('Y/m/d H:i:s', $token->getAccessTokenExpiresAt())->timestamp; //@phpstan-ignore-line - QB phpdoc wrong types!!
        $obj->refreshTokenExpiresAt = Carbon::createFromFormat('Y/m/d H:i:s', $token->getRefreshTokenExpiresAt())->timestamp; //@phpstan-ignore-line - QB phpdoc wrong types!!
        $obj->requires_reconnect = false;

        $obj->realmID = $token->getRealmID() ?: $obj->realmID;
        $obj->baseURL = $token->getBaseURL() ?: $obj->baseURL;

        $this->company->quickbooks = $obj;
        $this->company->save();
    }


    /// Data Access ///

    public function totalRecords(string $entity): int
    {
        $whereClause = $this->buildEntityWhereClause($entity);
        $query = "select count(*) from $entity" . ($whereClause ? " WHERE $whereClause" : "");
        return (int) $this->query($query);
    }

    private function queryData(string $query, int $start = 1, $limit = 1000): array
    {
        return (array) $this->query($query, $start, $limit);
    }

    public function query(string $query, ?int $start = null, ?int $limit = null): mixed
    {
        return $this->execute(function () use ($query, $start, $limit): mixed {
            if ($start === null && $limit === null) {
                return $this->sdk->Query($query);
            }

            return $this->sdk->Query($query, $start, $limit);
        });
    }

    public function fetchById(string $entity, $id)
    {
        return $this->findById($entity, $id);
    }

    public function findById(string $entity, mixed $id): mixed
    {
        return $this->execute(fn () => $this->sdk->FindById($entity, $id));
    }

    public function add(mixed $entity): mixed
    {
        return $this->execute(fn () => $this->sdk->Add($entity));
    }

    public function update(mixed $entity): mixed
    {
        return $this->execute(fn () => $this->sdk->Update($entity));
    }

    public function voidEntity(mixed $entity): mixed
    {
        return $this->execute(fn () => $this->sdk->Void($entity));
    }

    public function fetchRecordsPage(string $entity, int $startPosition = 1, int $limit = self::MAXRESULTS): array
    {
        if (!in_array($entity, $this->entities)) {
            return [];
        }

        $startPosition = max(1, $startPosition);
        $limit = max(1, min($limit, self::MAXRESULTS));

        $whereClause = $this->buildEntityWhereClause($entity);
        $baseQuery = "select * from $entity" . ($whereClause ? " WHERE $whereClause" : "");

        return $this->normalizeRecords($this->query($baseQuery, $startPosition, $limit));
    }

    public function fetchRecords(string $entity, int $max = 100000): array
    {

        if (!in_array($entity, $this->entities)) {
            return [];
        }

        $records = [];
        $start = 0;
        $limit = 1000;
        try {

            // Build query with filters for specific entities
            $whereClause = $this->buildEntityWhereClause($entity);
            $baseQuery = "select * from $entity" . ($whereClause ? " WHERE $whereClause" : "");

            $total = $this->totalRecords($entity);
            $total = min($max, $total);

            // Step 3 & 4: Get chunks of records until the total required records are retrieved
            do {
                $limit = min(self::MAXRESULTS, $total - $start);

                $recordsChunk = $this->queryData($baseQuery, $start, $limit);
                if (empty($recordsChunk)) {
                    break;
                }

                $records = array_merge($records, $recordsChunk);
                $start += $limit;

            } while ($start < $total);
            if (empty($records)) {
                throw new \Exception("No records retrieved!");
            }

        } catch (\Throwable $th) {
            nlog("Fetch Quickbooks API Error: {$th->getMessage()}");
        }

        return $records;
    }

    private function normalizeRecords(mixed $records): array
    {
        if (empty($records)) {
            return [];
        }

        if (is_array($records)) {
            return array_is_list($records) ? $records : [$records];
        }

        return [$records];
    }

    /**
     * Build WHERE clause for entity-specific filtering.
     *
     * For Items, we only include types that can be used as line items on invoices.
     * QuickBooks doesn't support != operator, so we use IN with valid types.
     *
     * @param string $entity The QuickBooks entity name
     * @return string The WHERE clause (without the WHERE keyword) or empty string
     */
    private function buildEntityWhereClause(string $entity): string
    {
        if ($entity === 'Item') {
            // Only include item types that can be used as line items on invoices/estimates
            // Valid types: Service, NonInventory, Inventory
            // Excluded types: Category, Group, Bundle (not universally supported)
            // See: https://developer.intuit.com/app/developer/qbo/docs/api/accounting/all-entities/item
            return "Type IN ('Service', 'NonInventory', 'Inventory')";
        }

        return '';
    }

    private function rateLimiter(): ?QuickbooksRateLimiter
    {
        $realm = $this->company->quickbooks->realmID ?? null;

        if (! $realm) {
            return null;
        }

        return $this->rate_limiter ??= new QuickbooksRateLimiter($realm);
    }

    private function execute(callable $callback): mixed
    {
        $this->ensureTokenFresh();

        $limiter = $this->rateLimiter();
        $request_token = null;

        if ($limiter) {
            if (! $limiter->waitForCapacity(self::RATE_LIMIT_MAX_WAIT_SECONDS)) {
                throw new \RuntimeException('QuickBooks rate limit: capacity unavailable after wait');
            }

            $request_token = $limiter->acquireRequest();
            $limiter->trackRequest();
        }

        try {
            return $callback();
        } catch (\Throwable $e) {

            if ($limiter && QuickbooksRateLimiter::isRateLimitException($e)) {
                $limiter->enterBackoff(60);

                if ($limiter->waitForCapacity(self::RATE_LIMIT_MAX_WAIT_SECONDS)) {
                    return $callback();
                }

                throw $e;
            }

            if (! $this->isAuthenticationFailure($e)) {
                throw $e;
            }

            $this->refreshTokenLocked(true);

            return $callback();
        } finally {
            if ($limiter && $request_token) {
                $limiter->releaseRequest($request_token);
            }
        }
    }

    private function ensureTokenFresh(): void
    {
        if ($this->tokenNeedsRefresh()) {
            $this->refreshTokenLocked();
        }
    }

    private function tokenNeedsRefresh(int $leewaySeconds = self::ACCESS_TOKEN_REFRESH_LEEWAY_SECONDS): bool
    {
        if (! $this->company->quickbooks || $this->company->quickbooks->requires_reconnect) {
            return false;
        }

        if ($this->company->quickbooks->accessTokenExpiresAt === 0) {
            return false;
        }

        return $this->company->quickbooks->accessTokenExpiresAt <= time() + $leewaySeconds;
    }

    private function refreshTokenExpired(): bool
    {
        if (! $this->company->quickbooks) {
            return true;
        }

        return $this->company->quickbooks->refreshTokenExpiresAt > 0
            && $this->company->quickbooks->refreshTokenExpiresAt < time();
    }

    private function markRequiresReconnect(): void
    {
        if (! $this->company->quickbooks) {
            return;
        }

        $quickbooks = $this->company->quickbooks;
        $quickbooks->requires_reconnect = true;
        $this->company->quickbooks = $quickbooks;
        $this->company->save();
    }

    private function preserveConnectionContext(OAuth2AccessToken $token, QuickbooksSettings $settings): void
    {
        if ($token->getRealmID() === '' && $settings->realmID !== '') {
            $token->setRealmID($settings->realmID);
        }

        if ($token->getBaseURL() === '' && $settings->baseURL !== '') {
            $token->setBaseURL($settings->baseURL);
        }
    }

    private function isAuthenticationFailure(\Throwable $e): bool
    {
        if ($e instanceof ServiceException && (int) $e->getCode() === 401) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, '401')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'authenticationfailed')
            || str_contains($message, 'invalid_token')
            || str_contains($message, 'token expired');
    }

    private function isRefreshTokenFailure(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'invalid_grant')
            || str_contains($message, 'refresh token')
            || str_contains($message, 'refresh_token');
    }
}
