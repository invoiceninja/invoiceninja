# Client portal Playwright tests

This suite uses the same seeded backend environment and account-lane convention
as `../ui`. `VITE_API_URL` is both the API endpoint and the default client portal
origin; set `CLIENT_PORTAL_BASE_URL` only when the portal is served elsewhere.

## Setup

Install Playwright’s Chromium browser (enough for the default project), then
copy the environment file:

```sh
npx playwright install --with-deps chromium
cp .env.playwright.example .env.playwright
```

Firefox is only needed for `--project=firefox` (`npx playwright install firefox`).
Browser binaries are cached under `~/.cache/ms-playwright` — they are **not**
downloaded per test. Per-test fixtures create a browser context and reuse a
worker-scoped API login.

The defaults expect the Laravel backend at `http://localhost:8000` and seeded
accounts named `user1@example.com`, `user2@example.com`, and so on, all with the
password `password`. On a dedicated test database, seed the same data used by
the UI suite when needed. The following command erases the configured database:

```sh
php artisan optimize
php artisan migrate:fresh --seed
php artisan db:seed --class=RandomDataSeeder
```

Run the suite with:

```sh
npm run test:e2e
```

PayPal REST tests live under `tests/e2e/client-portal-payments/paypal/`. They
scaffold their own company gateway from `PAYPAL_REST_KEYS` (create or update
gateway, verify auth, archive other active gateways for isolated specs, restore
in `afterEach`).

Run the full PayPal suite:

```sh
npm run test:e2e -- tests/e2e/client-portal-payments/paypal
```

Individual specs:

```sh
npm run test:e2e -- tests/e2e/client-portal-payments/paypal/invariants.spec.ts
npm run test:e2e -- tests/e2e/client-portal-payments/paypal/payments.spec.ts
npm run test:e2e -- tests/e2e/client-portal-payments/paypal/rff-payments.spec.ts
npm run test:e2e -- tests/e2e/client-portal-payments/rff-payments.spec.ts
```

Gateway checkout matrix (PayPal REST, Stripe, Authorize, etc.):

```sh
npm run test:e2e -- tests/e2e/client-portal-payments/gateways.spec.ts
npm run test:e2e -- tests/e2e/client-portal-payments/gateways.spec.ts -g "PayPal REST"
```

**VS Code:** use the Playwright extension with the repo's `playwright.config.ts`
(see `.vscode/settings.json`). `.env` is loaded from `fixtures.ts` and
`playwright.config.ts`. Escape double quotes inside JSON values (e.g.
`"buyerPassword":"Y\"!^0!aR"`) or use flat vars:
`PAYPAL_REST_CLIENT_ID`, `PAYPAL_REST_SECRET`, `PAYPAL_SANDBOX_BUYER_EMAIL`,
`PAYPAL_SANDBOX_BUYER_PASSWORD`.

Requires `PAYPAL_REST_KEYS` in `.env` — JSON with `clientId`, `secret`, and
`testMode`. Optional `buyerEmail` and `buyerPassword` enable sandbox payment
tests. The PayPal spec defaults to **headed** locally; set
`PLAYWRIGHT_HEADLESS=1` to force headless.

Defaults matter for the `about:blank` / `setting up "context"` failures:

- Workers default to **1** (`PLAYWRIGHT_WORKERS` to raise; capped by account lanes)
- Only the **chromium** project runs unless `PLAYWRIGHT_FIREFOX=1`
- Use `npm run test:e2e` / `:headed` / `:ui` — raw `npx playwright test` used to
  spawn 8 workers × Chromium+Firefox and wedge browser context creation
- Test timeout is 60s so worker-scoped API login does not steal the first
  test’s entire budget

### Zombie Chromium windows

Playwright reuses one browser per worker, but headed / UI mode can leave stuck
windows when a test times out mid-context or when `npm run test:e2e:ui` keeps a
`test-server` alive after you close the UI. Clear leftovers with:

```sh
npm run test:e2e:kill
```

Guest invitation tests must close their extra contexts in `finally` (use
`withGuestPortalPage`) so a failed assertion cannot leak a headed window.

## Spec files

| Spec | Coverage |
| --- | --- |
| `client-portal.spec.ts` | Login, sidebar links, invitations, logout |
| `client-portal-auth.spec.ts` | Password login, forgot/reset, magic link, self-registration |
| `client-portal-invoices.spec.ts` | List, filters, detail, downloads, bulk actions, Pay Now (default/smooth), bulk pay, terms/signature gates (default dropdown + bulk + smooth Flow2), password-protected invitations |
| `client-portal-pdf-previews.spec.ts` | Real PDF preview resolution for invoices, quotes, credits, and recurring invoices |
| `client-portal-payments/gateways.spec.ts` | Gateway checkout matrix (PayPal REST, Stripe, Authorize, etc.) |
| `client-portal-payments/paypal/payments.spec.ts` | PayPal REST per-method: Pay Now / smooth flow, checkout UI, sandbox payment, vault (default + smooth) |
| `client-portal-payments/paypal/invariants.spec.ts` | PayPal REST method registry and helper invariants |
| `client-portal-payments/paypal/required-client-info.spec.ts` | PayPal REST required client info gating — default + smooth flow |
| `client-portal-payments/paypal/rff-payments.spec.ts` | PayPal REST empty-client RFF then sandbox payment — default + smooth flow |
| `client-portal-payments/rff-payments.spec.ts` | Non-PayPal gateways: empty-client RFF then checkout (Stripe payment when configured) |
| `client-portal-entities.spec.ts` | Dashboard, payments, credits, projects, statement, pre-payments, payment methods |
| `client-portal-quotes.spec.ts` | Approve/reject, signature, filters, bulk actions |
| `client-portal-recurring.spec.ts` | Auto-bill, cancellation, attachments |
| `client-portal-subscriptions.spec.ts` | Purchase v1/v2 entry, plan-switch links |
| `client-portal-documents.spec.ts` | Upload/download, entity tabs, bulk zip (opt-in), cross-client block |
| `client-portal-tasks.spec.ts` | Task list / detail |
| `client-portal-profile.spec.ts` | Profile edit / disable |
| `client-portal-access.spec.ts` | Cross-client invoice/document isolation, portal disable |
| `client-portal-modules.spec.ts` | Module / sidebar gating |
| `client-portal-invitations.spec.ts` | Guest invitation links, prefs, set-password |
| `client-portal-settings.spec.ts` | `../ui` portal toggles: over/under payment, credit apply (`option`/`always`/`off`), uploads, branding, mobile HTML + product notes preference, unlock docs after payment, task visibility, registration fields |
| `account-management.spec.ts` | Account management API on `small@example.com`: plan catalog, upgrade/downgrade quotes, pro rata pricing, quote validation, trials, all downgrade paths, billing portal data (users/invoices/methods), Stripe checkout including annual and month→year upgrades (requires `invoiceninja/admin-api` and owner access to `/api/client/account_management/*`; Stripe checkout tests also need `STRIPE_KEYS`) |

## Skip conditions

| Condition | Specs affected |
| --- | --- |
| Missing `STRIPE_KEYS` / other gateway env + company gateway | Full gateway checkout, bulk Pay Now completion, Stripe payment-method add |
| Missing `PAYPAL_REST_KEYS` | PayPal REST Playwright tests skip |
| Missing `buyerEmail` / `buyerPassword` in `PAYPAL_REST_KEYS` | PayPal wallet sandbox payment tests skip; checkout UI tests still run |
| PayPal Venmo | `completes sandbox payment` skips — Venmo presents a PayPal bot challenge that cannot be automated; checkout UI / Pay Now tests still run |
| PayPal Pay Later unavailable for transaction amount | Pay Later completion test fails or skips when Pay in 4 is not offered |
| PayPal Pay Later Pay in 4 confirmation | After selecting Pay in 4, PayPal may show an optional **Agree and Apply** step (`#confirmInfoContinue`); e2e clicks it when present |
| PayPal Pay Later Pay in 4 autopay | Pay in 4 may show **Choose an autopay option** (`#autopay`); e2e keeps the default instrument or selects the first enabled `AutopaySelectionInput` radio, checks the autopay disclosure checkbox (`payLaterApplicationAutopayDisclosureContent`), then continues |
| Stale Authorize.Net Accept.js key | Authorize.Net e2e skips when the public client key meta is empty |
| PayPal Express-only company gateway | PayPal tests skip — Express driver was removed; seed PayPal REST (`80af24a6…065`) to cover PayPal |
| Remote app missing PaymentMethod multi-gateway fix | Authorize/Checkout Pay Now options skip until `PaymentMethod::getMethods()` fix is deployed |
| `PLAYWRIGHT_ALLOW_BULK_ZIP` unset | Document bulk zip download (single-worker PHP HTTP self-fetch deadlock) |
| DocuNinja inactive / signature pad replaced | Quote canvas signature may skip |
| Cloudflare Turnstile on registration | Self-registration may skip |
| Local artisan tinker cannot see remote API contacts | Password reset token + magic link may skip |
| `APP_KEY` mismatch with remote | Invitation set-password hash may fail |
| admin-api missing or owner blocked from account management routes | `account-management.spec.ts` skips |
| Missing `STRIPE_KEYS` / gateway key | Account management upgrade checkout, trial conversion, annual/term upgrades, and PaymentIntent guardrails skip |
| Account outside 14-day new-account window | Account management `start_trial` API test skips (`small@example.com` is usually ineligible) |
| `QUEUE_CONNECTION=redis` without a worker | Account management invoice PDF download skips after export timeout |

Still thin / env-heavy relative to `../ui` Client Portal + Online Payments panels:

- `signature_on_pdf` (PDF binary assertion)
- `use_unapplied_payment` (server auto-apply; little portal UI)
- Hosted `portal_mode` / subdomain / custom domain login URLs
- Subscription trial / per-seat / `registration_required`
- Vendor portal upload toggle (`vendor_portal_enable_uploads`)

Not exposed in the portal UI (do not add tests until product changes):

- Invoice draft / cancelled list filters (query excludes them)
- Credit bulk actions (no portal checkboxes)
- Profile localization UI (route exists without controller/UI)

## Creating API resources

Import the custom fixture to create any API entity. Created resources with an
`id` are automatically archived and deleted after the test.

```ts
import { expect, test, uniqueName } from './fixtures';

test('creates a client', async ({ api }) => {
    const client = await api.createEntity('clients', {
        name: uniqueName('portal-client'),
        contacts: [{
            first_name: 'Portal',
            last_name: 'Client',
            email: `${uniqueName('portal-client')}@example.com`,
        }],
    });

    expect(client.id).toBeTruthy();
});
```

Use `api.context.request` for other authenticated API calls. Pass
`{ cleanup: false }` as the third `createEntity` argument only when a test must
preserve the resource, or call `api.trackEntity(type, id)` for resources created
outside the fixture.

For entities whose create endpoint supplies required defaults, use
`api.createEntityFromBlank(type, overrides)`. It fetches `/api/v1/{type}/create`,
merges the overrides, creates the entity, and tracks it for cleanup.

Auth helpers that need server state (`portal-auth-helpers.ts`) call
`php artisan tinker --execute` for password-reset tokens and magic links.

## Driving portal UI

The portal mixes server-rendered Blade with Livewire and Alpine, so a click can
land before the behaviour it triggers is bound. `client-portal-helpers.ts`
provides the waits that avoid this:

- `waitForAlpine` before dropdowns and modals (`@click` handlers only exist once
  the bundle has run; clicking earlier silently does nothing).
- `waitForLivewire`, `fillLivewireInput`, and `submitLivewireComponent` for
  Livewire forms, which set component state directly instead of relying on
  `fill` reaching the component.
- `clearPortalOverlays` when a fixed element (cookie consent banner, debug bar)
  can intercept a click.
