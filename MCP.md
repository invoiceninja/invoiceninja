# Native Invoice Ninja MCP

This opt-in server addresses [discussion #13249](https://github.com/invoiceninja/invoiceninja/discussions/13249). It runs inside Invoice Ninja using the official `laravel/mcp` package. The HTTP endpoint dispatches native application requests through the existing REST routes; no external Invoice Ninja wrapper, second permission store, or alternate business logic is involved.

## Enable and connect

Set `MCP_ENABLED=true` in the application's private environment and refresh the Laravel configuration cache if it is enabled. The default is disabled. Use:

- URL: `https://your-invoice-ninja.example/api/v1/mcp`
- Transport: stateless Streamable HTTP, POST JSON-RPC; authenticated GET/DELETE return 405.
- Header: `Authorization: Bearer <existing-company-api-token>` or `X-API-TOKEN: <existing-company-api-token>`.
- Header: `X-API-SECRET` when the existing optional `ninja.api_secret` setting requires it.
- Accept: `application/json, text/event-stream`.

Conflicting bearer and X-API-TOKEN credentials are rejected. No query-string credentials, OAuth client registry, or stdio server is added. Use HTTPS outside a private local test network. An Origin header must match the endpoint origin or an explicitly configured comma-separated `MCP_ALLOWED_ORIGINS` entry.

`TokenAuth` resolves the CompanyToken, User and CompanyUser for every request, including discovery. Existing token revocation, soft-deleted users, locked memberships, hosted account restrictions and multi-database tenant selection apply. Session IDs cannot preserve or override a previously authenticated identity.

## Tools and permissions

One tool represents one domain. `operation` selects a specific REST operation, `parameters` supplies the named path parameters, `query` supplies the original filters/includes/pagination, and `data` supplies the original REST JSON payload. Discovery reuses summaries and request field types from the existing `openapi/api-docs.yaml`, resolving local references only. Field hints are combined per domain and nested definitions are kept compact; operation-specific required fields and validation remain in the original FormRequests. Routes missing from OpenAPI retain generic JSON inputs rather than maintaining a second API schema. The compact domain catalog fits in one default discovery page; cursor pagination remains supported.

`app/Mcp/ReviewedOperations.php` explicitly lists the reviewed HTTP method, URI, controller and mutation hint. A new route, method or controller is never automatically exposed. Adding an operation requires reviewing its authorization and tenant scope, adding its allowlist entry and SDK scenario, and rerunning the inventory and tests.

For example, call `clients` with:

```json
{"operation":"index","query":{"page":1,"per_page":20}}
```

To update a client:

```json
{"operation":"update","parameters":{"client":"<hashed-client-id>"},"data":{"name":"Updated client"}}
```

Tool availability is not an authorization grant. Every invocation runs the HTTP middleware, tenant-aware bindings, original FormRequest authorization/validation, controllers, repositories and transformers. Ownership and assignment are preserved: removing `edit_client`, for example, does not prevent an owner or assignee from editing where EntityPolicy allows it. Read-only users must be evaluated against the actual entity policy. HTTP GET is not treated as proof that an operation is read-only; invoice/quote/credit/purchase-order `action` endpoints can mutate.

Mixed read/write domain tools have conservative mutation annotations. Existing action-specific restrictions, such as payment permissions or disabled email permissions, remain in the original application code. This feature does not repair or broaden those REST permissions.

Optional extra restrictions are configured in `config/mcp.php`, keyed by internal `company_id:user_id`:

```php
'disabled_tools' => [
    '123:456' => ['payments', 'invoices.action', 'clients.update'],
],
```

A tool name disables that entire domain; `tool.operation` disables that operation. Restrictions affect discovery and direct invocation. They never enable an operation denied by REST. No database migration or permission-management UI is required.

## Results and errors

Successful calls return structured content and equivalent JSON text with `status` and the original REST `body`, preserving pagination metadata. REST validation/authorization failures set `isError` and preserve the actual REST status (including existing 401 policy denials). Invalid/revoked tokens and locked users are rejected by the authentication middleware before protocol handling. Protocol errors use Laravel MCP's JSON-RPC handling.

URLs, controller names, HTTP methods and headers cannot be supplied by a tool caller. The resolved REST route template and controller must still match the reviewed operation after path parameters are substituted, preventing static routes from being selected through an entity ID. Method overrides, credential arguments, account-wide selectors and `include_static` are rejected. Includes are normalized before checking every relationship segment; account, company-administration and credential expansions are forbidden in singular and plural forms. Includes accept comma-separated or array values containing dot-separated relationship names; Fractal modifiers are unsupported. Actions are normalized and checked against the reviewed JSON action list in `InputGuard` in every path, query and body source. Conflicting selectors, unknown actions, file-producing actions and placeholder history actions are rejected before dispatch. The original REST validators still decide which actions each endpoint permits. Multipart inputs, files, streamed exports and HTML previews use REST.

The default output limit is 1 MiB. Reduce the page size/includes if a result exceeds it. An oversized/non-JSON result reports `operation_may_have_completed` and an entity ID when available: an error response is not a promise that the original controller rolled back its work. Read the record before retrying a mutation.

## Inventory and coverage

The route-derived inventory is generated by `php tools/mcp/manifest.php` into ignored `.local/api-manifest.json`; `node tools/mcp/coverage.mjs` joins it with actual SDK results into `.local/coverage.json`. Generated reports are not committed. Every registered application `api`-group or `/api/` verb (except HEAD) has a tool mapping or a reason for exclusion. PATCH updates map to the same application update operation, dispatched with PUT; aliases are identified in the report. MCP protocol routes are recorded separately. Every reviewed operation has a matching OpenAPI method/path definition, enforced by `OpenApiTest`. Edit the existing OpenAPI source fragments and run `php artisan ninja:openapi` when adding or changing definitions. The inventory's `openapi_documented` field also exposes any future gaps.

The business domains include clients, invoices, quotes, credits, payments/refunds, expenses, recurring entities, products, vendors, purchase orders, projects, tasks/time tracking, statuses, categories, tax rates, payment terms, locations, group settings, bank transactions/rules, document metadata, designs, subscriptions, webhook records, activities, company ledger, email delivery/history and search.

Excluded workflows include global statics/licensing, account-wide user listings, credential/account/company administration, interactive password confirmations, external-provider enrollment/synchronization/checkout, file uploads/downloads/imports/exports/reports, previews and unimplemented controller placeholders. Task board reordering requires an authorization review before exposure. The existing task-status edit-form parameter mismatch is excluded; its show/update endpoints remain available. Each exclusion is attached to the exact method and URI in the manifest.

The E2E scenarios exercise every exposed `tool.operation` with synthetic data. They use representative inputs for parameterized action/bulk endpoints, not every possible input combination or every configured third-party payment provider. File-producing variants, including asynchronous `template` generation, are deliberately unsupported. Selectors without a corresponding REST implementation are also rejected: invoice entity actions `email`/`send_email` (use `invoices.bulk` or `emails.send`) and recurring-invoice bulk actions `auto_bill`/`auto_bill_enabled`.

## Local Docker validation

Requirements: Docker and Docker Compose; PowerShell for the supplied Windows setup, or equivalent shell commands. PHP, Composer, MariaDB and Node are provided by containers. Run `tools/mcp/setup.ps1` from the checkout. It creates a private, ignored `.env`, a dedicated project/database/vendor volume and synthetic fixtures for two companies with admin/viewer/member/locked roles. The script never reads production data. Existing `.env` files are preserved and must explicitly target the fixture database.

The runtime network is internal and publishes no host ports. Mail uses Laravel's array transport; refunds use `gateway_refund=false`; no payment gateway credentials or workers/schedulers are installed. A deterministic fake hostname satisfies webhook URL validation, while external delivery remains blocked by the internal network. Never add production credentials or attach this test stack to a production network.

Run the repository tests:

```text
docker compose --env-file .env -f tools/mcp/compose.yaml run --rm app php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Mcp tests/Unit/Mcp
```

Run all SDK scenarios:

```text
docker run --rm --network invoiceninja-mcp-test_default --mount type=bind,source=<absolute-checkout>,target=/work --workdir /work -e MCP_TEST_URL=http://app:8000/api/v1/mcp node:22-bookworm-slim node tools/mcp/full-e2e.mjs
```

Run `tools/mcp/e2e.mjs` on the same network for protocol/discovery and client/time-log persistence checks, then `php tools/mcp/verify-effects.php` in the app container for direct DB checks. Artifacts and credentials are written only under ignored `.local/`. `node tools/mcp/coverage.mjs` joins the route inventory with actual SDK outcomes.

This branch was validated on PHP 8.3 with PHPUnit 11, Laravel 12 and Laravel MCP 1.0.2. The existing CI matrix remains authoritative for other PHP versions. The upstream `composer lint` script names an uninstalled `pint` binary; use the PHP CS Fixer declared by Composer, limited to this feature with --config=tools/mcp/php-cs-fixer.php. The supplied PHPStan configuration references a missing `phpstan-baseline.neon`; focused level-5 Larastan analysis uses --configuration=tools/mcp/phpstan.neon. These baseline limitations are not reported as successful upstream checks.
