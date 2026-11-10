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

namespace App\Contracts\Bank;

/**
 * Unified contract for bank-account data providers.
 *
 * Every provider that wants to plug into Invoice Ninja's bank sync pipeline
 * must implement this interface. The methods are intentionally minimal and
 * map onto the lifecycle of an account connection:
 *
 *   1. getInstitutions()      — list the banks a user can connect to.
 *   2. createAuthorization()  — start an end-user consent/redirect flow.
 *   3. getAccountStatus()     — is the account ready / expired / suspended?
 *   4. getTransactions()      — pull new transactions for an account.
 *   5. getAccountMetadata()   — balance, currency, masked number, etc.
 *
 * The first implementation is the open-banking.io REST provider; Yodlee and
 * GoCardless/Nordigen can be adapted to it later without touching the
 * processing jobs.
 */
interface BankSyncProviderInterface
{
    /**
     * List the institutions (banks) the user can connect to.
     *
     * @param  string|null  $country  Optional ISO-3166 alpha-2 country filter (e.g. "DE", "GB").
     * @return array<int, array{id: string, name: string, logo?: string|null, countries?: string[]}>
     */
    public function getInstitutions(?string $country = null): array;

    /**
     * Begin an end-user authorization (consent) flow for an institution.
     *
     * Returns at minimum a redirect URL the user must visit to grant access,
     * plus any provider-side reference needed to complete the flow.
     *
     * @param  string  $institution_id  Institution id returned by getInstitutions().
     * @param  string  $redirect_uri    Absolute URL the provider redirects back to.
     * @return array{reference: string, link: string, expires_at?: string|null}
     */
    public function createAuthorization(string $institution_id, string $redirect_uri): array;

    /**
     * Return the current status of a connected account.
     *
     * Normalized statuses: "READY", "EXPIRED", "SUSPENDED", "PROCESSING",
     * "ERROR". Transient failures should resolve to "PROCESSING" so the
     * scheduler retries rather than disabling a healthy account.
     *
     * @return array{status: string, balance?: float|null, currency?: string|null}
     */
    public function getAccountStatus(string $account_id): array;

    /**
     * Fetch transactions for an account on or after $from_date.
     *
     * Each entry is a provider-normalized associative array:
     *   - transaction_id   : unique id from the provider
     *   - amount           : signed float (positive = credit, negative = debit)
     *   - date             : booking date, ISO-8601 (Y-m-d)
     *   - description      : human readable memo
     *   - counterparty     : counterparty account reference (iban/number) or null
     *   - counterparty_name: counterparty name or null
     *   - currency         : ISO-4217 code, e.g. "EUR"
     *   - base_type        : "CREDIT" or "DEBIT"
     *
     * @param  string|null  $from_date  Inclusive lower bound (Y-m-d), null = provider default.
     * @return array<int, array<string, mixed>>
     */
    public function getTransactions(string $account_id, ?string $from_date = null): array;

    /**
     * Return descriptive metadata for an account: holder, masked number,
     * iban, currency, product/name — whatever the provider exposes.
     *
     * @return array<string, mixed>
     */
    public function getAccountMetadata(string $account_id): array;
}
