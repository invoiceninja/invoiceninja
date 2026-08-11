# Client portal Playwright tests

This suite uses the same seeded backend environment and account-lane convention
as `../ui`. `VITE_API_URL` is both the API endpoint and the default client portal
origin; set `CLIENT_PORTAL_BASE_URL` only when the portal is served elsewhere.

## Setup

Install Playwright and its system dependencies, then copy the environment file:

```sh
npx playwright install --with-deps
cp .env.playwright.example .env.playwright
```

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

## Spec files

| Spec | Coverage |
| --- | --- |
| `client-portal.spec.ts` | Login, sidebar links, invitations, logout |
| `client-portal-auth.spec.ts` | Password login / recovery / registration UI |
| `client-portal-invoices.spec.ts` | List, filters, detail, invitation, bulk download, Pay Now (default + smooth), bulk pay |
| `client-portal-payments.spec.ts` | Gateway checkout (Stripe, PayPal, Authorize, Checkout.com, Braintree, GoCardless) |
| `client-portal-entities.spec.ts` | Dashboard, payments, credits, recurring, projects, statement, pre-payments, payment methods |
| `client-portal-quotes.spec.ts` | Approve / reject, filters, bulk actions |
| `client-portal-recurring.spec.ts` | Auto-bill opt-in / opt-out |
| `client-portal-documents.spec.ts` | Upload, download, tabs; bulk zip may skip on single-worker PHP |
| `client-portal-tasks.spec.ts` | Task list / detail |
| `client-portal-profile.spec.ts` | Profile edit |
| `client-portal-access.spec.ts` | Cross-client isolation |
| `client-portal-modules.spec.ts` | Module / sidebar gating |
| `client-portal-invitations.spec.ts` | Guest invitation links and preferences |

## Portal coverage

`client-portal.spec.ts` exercises the portal in Chromium and Firefox. It covers:

- the public entry point, login, password recovery, and conditional registration;
- every link rendered by a fully enabled client sidebar: dashboard, invoices,
  recurring invoices, payments, quotes, credits, payment methods, documents,
  tasks, projects, statement, subscriptions, and pre-payments;
- profile editing and logout;
- invoice, recurring invoice, payment, quote, credit, and project detail pages;
- invoice, recurring invoice, quote, and credit invitation links, plus Invoice
  Ninja's generated contact-key payment link; and
- invitation email-preference and unsubscribe pages.

The tests create disposable clients and documents through the API, then archive
and delete them in fixture teardown. Company and user-notification settings that
must be changed for a scenario are restored after the test. Embedded PDF blob
requests are blocked because this suite verifies page navigation and rendering,
not binary PDF generation.

## Skip conditions

Gateway-dependent tests skip unless both the env key and an enabled company
gateway exist:

| Env var | Gateway |
| --- | --- |
| `STRIPE_KEYS` | Stripe credit card |
| `PAYPAL_KEYS` / PayPal env | PayPal |
| `AUTHORIZE_KEYS` | Authorize.Net |
| `CHECKOUT_KEYS` | Checkout.com |
| `BRAINTREE_KEYS` | Braintree |
| `GOCARDLESS_KEYS` | GoCardless |

Examples that skip safely without keys: full Stripe checkout, bulk Pay Now
completion, and Stripe payment-method tokenization. Invoice detail Pay Now and
pre-payment submission still run when a company gateway is present, and skip
only the gateway-selection step when none is configured.

Document bulk zip download can deadlock on a single-worker PHP server that
self-fetches over HTTP. Prefer multi-worker PHP in CI, or an app change to use
`diskPath()` in `DocumentController::downloadMultiple`.

Subscription purchase, magic-link / password-reset mail flows, and DocuNinja
embeds need additional provider or mail state and remain environment-specific.

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
