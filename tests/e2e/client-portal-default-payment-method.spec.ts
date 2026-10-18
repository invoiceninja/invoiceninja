import { type Page } from '@playwright/test';
import { runArtisan } from './artisan-helpers';
import { createPortalClient, logInPortalClient, type PortalClient } from './client-portal-helpers';
import { expect, test as base } from './fixtures';
import { decodePrimaryKey } from './hash-helpers';

interface Token {
    id: number;
    hashed_id: string;
}

interface TokenState {
    id: number;
    client_id: number;
    company_id: number;
    is_default: boolean | number;
    is_deleted: boolean | number;
    deleted_at: number | null;
    updated_at: number;
}

interface Scenario {
    owner: PortalClient;
    other: PortalClient;
    tokens: Token[];
    gatewayId: number;
}

const componentSelector = '[wire\\:snapshot]';
const saveSelector = 'form[wire\\:submit="makeDefault"] button';

const test = base.extend<{ scenario: Scenario }>({
    scenario: async ({ api }, use) => {
        const owner = await createPortalClient(api);
        const other = await createPortalClient(api);
        // Only these two new clients receive synthetic tokens. No gateway API is used.
        const seeded = JSON.parse(runArtisan(`
            echo \\Illuminate\\Support\\Facades\\DB::transaction(function () {
                $clients = \\App\\Models\\Client::whereIn('id', [${decodePrimaryKey(owner.id)}, ${decodePrimaryKey(other.id)}])->orderBy('id')->get();
                if ($clients->count() !== 2) { throw new \\RuntimeException('Browser and Artisan must use the same database'); }
                $gateway = new \\App\\Models\\CompanyGateway();
                $gateway->company_id = $clients[0]->company_id;
                $gateway->user_id = $clients[0]->user_id;
                $gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
                $gateway->config = encrypt('{}');
                $gateway->fees_and_limits = [];
                $gateway->save();
                $tokens = [];
                foreach ($clients as $client) {
                    foreach ([true, false] as $default) {
                        $token = new \\App\\Models\\ClientGatewayToken();
                        $token->company_id = $client->company_id;
                        $token->client_id = $client->id;
                        $token->company_gateway_id = $gateway->id;
                        $token->gateway_type_id = \\App\\Models\\GatewayType::CREDIT_CARD;
                        $token->token = 'playwright-synthetic-' . \\Illuminate\\Support\\Str::uuid();
                        $token->meta = (object) ['brand' => 'Visa', 'last4' => '4242'];
                        $token->is_default = $default;
                        $token->is_deleted = false;
                        $token->save();
                        $tokens[] = ['id' => $token->id, 'hashed_id' => $token->hashed_id];
                    }
                }
                return json_encode(['tokens' => $tokens, 'gatewayId' => $gateway->id]);
            });
        `)) as Pick<Scenario, 'tokens' | 'gatewayId'>;

        try {
            await use({ owner, other, ...seeded });
        } finally {
            runArtisan(`
                \\App\\Models\\ClientGatewayToken::withTrashed()->whereIn('id', [${seeded.tokens.map(t => t.id).join(',')}])->forceDelete();
                \\App\\Models\\CompanyGateway::withTrashed()->whereKey(${seeded.gatewayId})->forceDelete();
            `);
        }
    },
});

function tokenState(scenario: Scenario): TokenState[] {
    return JSON.parse(runArtisan(`
        echo \\App\\Models\\ClientGatewayToken::withTrashed()
            ->whereIn('id', [${scenario.tokens.map(t => t.id).join(',')}])->orderBy('id')
            ->get(['id', 'client_id', 'company_id', 'is_default', 'is_deleted', 'deleted_at', 'updated_at'])->toJson();
    `));
}

async function openMethod(page: Page, scenario: Scenario): Promise<string> {
    await logInPortalClient(page, scenario.owner);
    const response = await page.goto(`/client/payment_methods/${scenario.tokens[1].hashed_id}`);
    expect(response?.status()).toBe(200);
    await expect(page.locator(saveSelector)).toBeEnabled();
    return snapshot(page);
}

async function snapshot(page: Page): Promise<string> {
    return page.locator(componentSelector).evaluateAll(roots => {
        for (const root of roots) {
            const value = root.getAttribute('wire:snapshot');
            if (value && JSON.parse(value).memo.name === 'payment-methods.update-default-method') {
                return value;
            }
        }
        throw new Error('Default payment method component was not mounted');
    });
}

// Keep the original checksum and snapshot. Only the normal client update map changes.
// Read CSRF from the current page so a login change tests authorization, not CSRF expiry.
async function makeDefault(page: Page, signedSnapshot: string, updates: Record<string, unknown> = {}) {
    return page.evaluate(async ({ signedSnapshot, updates }) => {
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
            ?? document.querySelector('script[data-csrf]')?.getAttribute('data-csrf');
        if (!csrf) throw new Error('Missing current CSRF token');
        const endpoint = document.querySelector('script[data-update-uri]')?.getAttribute('data-update-uri') ?? '/livewire/update';
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Livewire': '' },
            body: JSON.stringify({
                _token: csrf,
                components: [{ snapshot: signedSnapshot, updates, calls: [{ path: '', method: 'makeDefault', params: [] }] }],
            }),
        });
        return { status: response.status, body: await response.text() };
    }, { signedSnapshot, updates });
}

function expectTokenUnavailable(response: { status: number; body: string }): void {
    // Invoice Ninja's exception handler maps ModelNotFoundException to 400 for JSON.
    expect(response.status).toBe(400);
    expect(JSON.parse(response.body).message).toContain('No query results for model [App\\Models\\ClientGatewayToken]');
}

test('switches the own-client default through the UI and safely repeats the action', async ({ page, scenario }) => {
    await openMethod(page, scenario);
    const otherBefore = tokenState(scenario).slice(2);
    const updated = page.waitForResponse(response => response.url().includes('/livewire/update') && response.request().method() === 'POST');
    await page.locator(saveSelector).click();
    expect((await updated).status()).toBe(200);
    await expect(page.locator(saveSelector)).toBeDisabled();
    await page.reload();
    await expect(page.locator(saveSelector)).toBeDisabled();
    const state = tokenState(scenario);
    expect(state.map(token => Boolean(token.is_default))).toEqual([false, true, true, false]);
    expect(state.slice(2)).toEqual(otherBefore);

    expect((await makeDefault(page, await snapshot(page))).status).toBe(200);
    expect(tokenState(scenario)).toEqual(state);
});

for (const property of ['token_id', 'db'] as const) {
    test(`rejects ${property} tampering on a valid signed snapshot`, async ({ page, scenario }) => {
        const signedSnapshot = await openMethod(page, scenario);
        const before = tokenState(scenario);
        const value = property === 'token_id' ? scenario.tokens[3].id : 'untrusted-connection';
        const response = await makeDefault(page, signedSnapshot, { [property]: value });
        // Installed Livewire deliberately hides exception details behind an empty 419
        // outside debug mode. Debug mode exposes the locked-property exception.
        if (response.status === 419) {
            expect(response.body).toBe('');
        } else {
            expect(response.status).toBe(500);
            expect(response.body).toContain(`Cannot update locked property: [${property}]`);
        }
        expect(tokenState(scenario)).toEqual(before);
        // The same snapshot/session/CSRF succeeds without the mutation. A stale CSRF
        // token or invalid signature must not count as a passing authorization test.
        expect((await makeDefault(page, signedSnapshot)).status).toBe(200);
    });
}

test('rejects an old snapshot after logging in as another client', async ({ page, scenario }) => {
    const signedSnapshot = await openMethod(page, scenario);
    const before = tokenState(scenario);
    await logInPortalClient(page, scenario.other);
    const response = await makeDefault(page, signedSnapshot);
    expectTokenUnavailable(response);
    expect(tokenState(scenario)).toEqual(before);
});

test('rejects an old snapshot after logout with a fresh CSRF token', async ({ page, scenario }) => {
    const signedSnapshot = await openMethod(page, scenario);
    const before = tokenState(scenario);
    await page.goto('/client/logout');
    const response = await makeDefault(page, signedSnapshot);
    // Persistent authentication middleware rejects the unauthenticated JSON request.
    expect(response.status).toBe(401);
    expect(tokenState(scenario)).toEqual(before);
});

test('rejects a token with a foreign company even when its client ID still matches', async ({ page, scenario }) => {
    const signedSnapshot = await openMethod(page, scenario);
    // Deliberately inconsistent synthetic ownership isolates the company predicate
    // from the client predicate. No existing company's records are modified.
    const foreignCompanyId = Number(runArtisan(`
        $token = \\App\\Models\\ClientGatewayToken::findOrFail(${scenario.tokens[1].id});
        $company = \\App\\Models\\Company::factory()->create(['account_id' => $token->company->account_id]);
        $token->company_id = $company->id;
        $token->save();
        echo $company->id;
    `));
    try {
        const before = tokenState(scenario);
        expectTokenUnavailable(await makeDefault(page, signedSnapshot));
        expect(tokenState(scenario)).toEqual(before);
    } finally {
        runArtisan(`
            \\App\\Models\\ClientGatewayToken::withTrashed()->whereKey(${scenario.tokens[1].id})->forceDelete();
            \\App\\Models\\Company::withTrashed()->whereKey(${foreignCompanyId})->forceDelete();
        `);
    }
});

for (const state of ['archived', 'deleted', 'missing'] as const) {
    test(`rejects a token that becomes ${state} after mounting`, async ({ page, scenario }) => {
        const signedSnapshot = await openMethod(page, scenario);
        const mutation = state === 'archived' ? '$token->delete();'
            : state === 'deleted' ? '$token->is_deleted = true; $token->save();'
                : '$token->forceDelete();';
        runArtisan(`$token = \\App\\Models\\ClientGatewayToken::findOrFail(${scenario.tokens[1].id}); ${mutation}`);
        const before = tokenState(scenario);
        expectTokenUnavailable(await makeDefault(page, signedSnapshot));
        expect(tokenState(scenario)).toEqual(before);
    });
}
