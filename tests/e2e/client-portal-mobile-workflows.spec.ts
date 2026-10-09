import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve, basename } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { disableNativeDialogSupport } from './client-portal-mobile-compatibility';

test.beforeEach(async ({ page }) => {
    if (process.env.PLAYWRIGHT_LEGACY_DIALOGS) await disableNativeDialogSupport(page);
});

// Render the actual Blade components without creating invoices or charging a gateway.
const views = JSON.parse(execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
config(['ninja.environment' => 'selfhost', 'services.analytics.tracking_id' => null]);
$prefix = 'portal.ninja2020.';
$views = [];
foreach (['signature', 'terms'] as $name) {
    $views[$name] = view($prefix.'invoices.includes.'.$name, [
        'entities' => [(object) ['number' => 'Q-1', 'terms' => str_repeat('Long terms for mobile review. ', 150)]],
        'variables' => false, 'entity_type' => 'Quote',
    ])->render();
}
foreach (['user-input', 'reject-input'] as $name) {
    $views[$name] = view($prefix.'quotes.includes.'.$name)->render();
}
$views['livewire-signature'] = view($prefix.'components.livewire.signature')->render();
$client = new class {
    public function present() { return $this; }
    public function name() { return str_repeat('LongCompanyName', 8); }
    public function email() { return str_repeat('longemail', 8).'@example.test'; }
    public function phone() { return '0123456789'; }
};
$views['summary'] = view($prefix.'flow2.invoices-summary', [
    'isReady' => true, 'amount' => '$300.00', 'gateway_fee' => '$3.00', 'client' => $client,
    'invoices' => array_fill(0, 3, ['number' => 'INV-123', 'date' => '2026-10-01', 'due_date' => '2026-10-31', 'formatted_currency' => '$100.00', 'invoice_id' => 'test']),
])->render();
foreach (['none', 'partial', 'matomo', 'google', 'global', 'both', 'other-tenant'] as $mode) {
    config(['services.analytics.tracking_id' => $mode === 'global' ? 'G-TEST' : null]);
    $company = (object) [
        'company_key' => $mode === 'other-tenant' ? 'tenant-b' : 'tenant-a',
        'matomo_url' => in_array($mode, ['partial', 'matomo', 'both', 'other-tenant']) ? 'https://analytics.test/' : null,
        'matomo_id' => in_array($mode, ['matomo', 'both', 'other-tenant']) ? '1' : null,
        'google_analytics_key' => in_array($mode, ['google', 'both']) ? 'UA-TEST' : null,
    ];
    $views[$mode] = view($prefix.'components.analytics-consent', ['company' => $company])->render();
}
config(['ninja.environment' => 'hosted']);
$views['hosted'] = view($prefix.'components.analytics-consent', ['company' => $company, 'cleanLayout' => true])->render();
$company->matomo_url = null;
$company->matomo_id = null;
$views['hosted-none'] = view($prefix.'components.analytics-consent', ['company' => $company, 'cleanLayout' => true])->render();
$company->company_key = 'tenant-a';
$company->matomo_url = 'https://analytics.test/';
$company->matomo_id = '1';
$views['same-tenant-clean'] = view($prefix.'components.analytics-consent', ['company' => $company, 'cleanLayout' => true])->render();
auth()->guard('contact')->setUser(new App\\Models\\ClientContact(['first_name' => 'Test', 'last_name' => 'Contact']));
$views['attributed-contact'] = Illuminate\\Support\\Facades\\Blade::render(file_get_contents(resource_path('views/portal/ninja2020/components/analytics-consent.blade.php')), ['company' => $company]);
auth()->guard('vendor')->setUser(new App\\Models\\VendorContact(['first_name' => 'Test', 'last_name' => 'Vendor']));
$views['attributed-vendor'] = Illuminate\\Support\\Facades\\Blade::render(file_get_contents(resource_path('views/portal/ninja2020/components/analytics-consent.blade.php')), ['company' => $company, 'analyticsGuard' => 'vendor']);
echo json_encode($views);
`], { encoding: 'utf8' }));
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = readFileSync(resolve('public/build', manifest['resources/sass/app.scss'].file), 'utf8');
const analytics = readFileSync('resources/js/clients/analytics-consent.js', 'utf8').replace('export function', 'function');

async function mount(page: Page, html: string, entry?: string) {
    await page.route('http://portal.test/**', route => {
        const url = new URL(route.request().url());
        if (url.pathname.startsWith('/assets/')) {
            return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('public/build/assets', basename(url.pathname))) });
        }
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css} .bg-primary { background-color: #1c64f2; }</style></head><body data-portal="client">${html}</body></html>` });
    });
    await page.goto('http://portal.test/');
    if (entry) {
        await page.addScriptTag({ type: 'module', url: `http://portal.test/${manifest[entry].file}` });
    }
}

async function draw(page: Page) {
    await expect(page.locator('#signature-pad')).toBeVisible();
    const box = await page.locator('#signature-pad').boundingBox();
    await page.mouse.move(box!.x + 25, box!.y + 35);
    await page.mouse.down();
    await page.mouse.move(box!.x + 130, box!.y + 80, { steps: 12 });
    await page.mouse.up();
    await expect(page.locator('#signature-next-step')).toBeEnabled();
}

function approvalHtml(input = false) {
    return `<meta name="require-quote-signature" content="1"><meta name="show-quote-terms" content="1"><meta name="accept-user-input" content="${Number(input)}"><meta name="docuninja-active" content="0">
        <form id="approve-form"><input name="signature"><input name="user_input"></form><button id="approve-button">Approve</button>
        ${views['user-input']}${views.signature}${views.terms}`;
}

for (const width of [320, 375, 390]) {
    test(`approval remains usable at ${width}px, including cancel, resize and long terms`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 667 });
        await mount(page, approvalHtml(), 'resources/js/clients/quotes/approve.js');
        await page.evaluate(() => {
            (window as any).submissions = 0;
            (document.getElementById('approve-form') as HTMLFormElement).submit = () => { (window as any).submissions++; };
        });
        await page.locator('#approve-button').click();
        await expect(page.locator('html')).toHaveClass(/portal-dialog-open/);
        if (process.env.PLAYWRIGHT_LEGACY_DIALOGS) {
            await expect(page.locator('#displaySignatureModal')).toHaveClass(/portal-dialog-fallback/);
            await expect(page.locator('#displaySignatureModal + .backdrop')).toBeVisible();
        } else {
            expect(await page.evaluate(() => performance.getEntriesByType('resource').some(entry => entry.name.includes('dialog-polyfill')))).toBe(false);
        }
        await page.locator('#close-signature-button').click();
        await expect(page.locator('#approve-button')).toBeEnabled();
        await expect(page.locator('html')).not.toHaveClass(/portal-dialog-open/);
        await expect(page.locator('#approve-button')).toBeFocused();
        await page.locator('#approve-button').click();
        const bounds = await page.locator('#signature-pad').boundingBox();
        expect(bounds!.x).toBeGreaterThanOrEqual(0);
        expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(width);
        await draw(page);
        if (width === 320) await page.screenshot({ path: testInfo.outputPath('mobile-signature.png') });
        await page.setViewportSize({ width: 667, height: 320 });
        await expect.poll(() => page.locator('#signature-pad').evaluate((el: HTMLCanvasElement) =>
            el.getContext('2d')!.getImageData(0, 0, el.width, el.height).data.some((value, index) => index % 4 === 3 && value > 0),
        )).toBe(true);
        await page.locator('#signature-next-step').click();
        await expect(page.locator('#displayTermsModal')).toBeVisible();
        const action = await page.locator('#accept-terms-button').boundingBox();
        expect(action!.y).toBeGreaterThanOrEqual(0);
        expect(action!.y + action!.height).toBeLessThanOrEqual(320);
        await page.keyboard.press('Escape');
        await expect(page.locator('#approve-button')).toBeEnabled();
        await page.locator('#approve-button').click();
        await page.locator('#signature-next-step').click();
        await page.locator('#accept-terms-button').click();
        expect(await page.evaluate(() => (window as any).submissions)).toBe(1);
    });
}

test('PO input, signature clear and rejection can be cancelled and retried', async ({ page }) => {
    await mount(page, approvalHtml(true) + '<button id="reject-button">Reject</button><form id="reject-form"><input name="user_input"></form>' + views['reject-input'], 'resources/js/clients/quotes/approve.js');
    await page.addScriptTag({ type: 'module', url: `http://portal.test/${manifest['resources/js/clients/quotes/reject.js'].file}` });
    await page.locator('#approve-button').click();
    await expect(page.locator('#reject-button')).toBeDisabled();
    await page.locator('#close-input-button').click();
    await expect(page.locator('#reject-button')).toBeEnabled();
    await page.locator('#approve-button').click();
    await page.locator('#user_input').fill('PO-123');
    await page.locator('#input-next-step').click();
    await draw(page);
    await page.locator('#clear-signature').click();
    await expect(page.locator('#signature-next-step')).toBeDisabled();
    await page.keyboard.press('Escape');
    await expect(page.locator('#reject-button')).toBeEnabled();
    await expect(page.locator('#approve-form input[name="user_input"]')).toHaveValue('PO-123');
    await page.locator('#reject-button').click();
    await expect(page.locator('#approve-button')).toBeDisabled();
    await page.locator('#reject-close-button').click();
    await expect(page.locator('#reject-button')).toBeEnabled();
    await expect(page.locator('#approve-button')).toBeEnabled();
});

test('a dialog opening error restores both quote actions and permits retry', async ({ page }) => {
    await mount(page, approvalHtml() + '<button id="reject-button">Reject</button>', 'resources/js/clients/quotes/approve.js');
    await page.evaluate(() => {
        const dialog = document.getElementById('displaySignatureModal') as HTMLDialogElement;
        const original = dialog.showModal;
        dialog.showModal = () => {
            if (original) dialog.showModal = original;
            else delete (dialog as any).showModal;
            throw new Error('Test opening failure');
        };
    });
    page.once('dialog', dialog => dialog.dismiss());
    await page.locator('#approve-button').click();
    await expect(page.locator('#approve-button')).toBeEnabled();
    await expect(page.locator('#reject-button')).toBeEnabled();
    await expect(page.locator('html')).not.toHaveClass(/portal-dialog-open/);
    await page.locator('#approve-button').click();
    await expect(page.locator('#displaySignatureModal')).toBeVisible();
    await expect(page.locator('#reject-button')).toBeDisabled();
});

if (process.env.PLAYWRIGHT_LEGACY_DIALOGS) {
    test('a failed polyfill download leaves quote actions available', async ({ page }) => {
        await mount(page, approvalHtml() + '<button id="reject-button">Reject</button>', 'resources/js/clients/quotes/approve.js');
        await page.route('**/dialog-polyfill*', route => route.abort());
        const error = page.waitForEvent('dialog');
        await page.locator('#approve-button').click();
        await (await error).dismiss();
        await expect(page.locator('#approve-button')).toBeEnabled();
        await expect(page.locator('#reject-button')).toBeEnabled();
        await expect(page.locator('html')).not.toHaveClass(/portal-dialog-open/);
    });
}

test('purchase order acceptance shares the dialog fallback and cancellation', async ({ page }) => {
    await mount(page, approvalHtml().replaceAll('quote-signature', 'purchase_order-signature').replaceAll('quote-terms', 'purchase_order-terms'), 'resources/js/clients/purchase_orders/accept.js');
    await page.locator('#approve-button').click();
    await draw(page);
    await page.locator('#signature-next-step').click();
    await expect(page.locator('#displayTermsModal')).toBeVisible();
    await page.locator('#close-terms-button').click();
    await expect(page.locator('#approve-button')).toBeEnabled();
    await expect(page.locator('html')).not.toHaveClass(/portal-dialog-open/);
});

test('signature resizing catches orientation changes while the dialog is closed', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await mount(page, approvalHtml(), 'resources/js/clients/quotes/approve.js');
    await page.locator('#approve-button').click();
    await draw(page);
    await page.locator('#close-signature-button').click();
    await page.setViewportSize({ width: 667, height: 375 });
    await page.locator('#approve-button').click();
    await expect(page.locator('#signature-pad')).toBeVisible();
    await expect.poll(() => page.locator('#signature-pad').evaluate((canvas: HTMLCanvasElement) =>
        canvas.width === Math.round(canvas.clientWidth * devicePixelRatio),
    )).toBe(true);
    await expect(page.locator('#signature-next-step')).toBeEnabled();
});

for (const docuninja of [false, true]) {
    test(`approval blocks rejection ${docuninja ? 'during embedded signing' : 'while submitting'}`, async ({ page }) => {
        const html = approvalHtml()
            .replace('name="show-quote-terms" content="1"', 'name="show-quote-terms" content="0"')
            .replace('name="require-quote-signature" content="1"', `name="require-quote-signature" content="${Number(docuninja)}"`)
            .replace('name="docuninja-active" content="0"', `name="docuninja-active" content="${Number(docuninja)}"`);
        await mount(page, html + '<button id="reject-button">Reject</button><form id="reject-form"><input name="user_input"></form><div id="pdf-slot-container">PDF</div><div id="docuninja-container" class="hidden">Sign document</div>' + views['reject-input'], 'resources/js/clients/quotes/approve.js');
        await page.addScriptTag({ type: 'module', url: `http://portal.test/${manifest['resources/js/clients/quotes/reject.js'].file}` });
        await page.evaluate(() => {
            (window as any).submissions = 0;
            (document.getElementById('approve-form') as HTMLFormElement).submit = () => { (window as any).submissions++; };
        });
        await page.locator('#approve-button').click();
        await expect(page.locator('#approve-button')).toBeDisabled();
        await expect(page.locator('#reject-button')).toBeDisabled();
        // The embedded signer leaves the underlying actions visible. A second
        // native click must not start a competing workflow.
        await page.locator('#reject-button').evaluate((button: HTMLButtonElement) => button.click());
        await expect(page.locator('#displayRejectModal')).not.toBeVisible();
        expect(await page.evaluate(() => (window as any).submissions)).toBe(docuninja ? 0 : 1);
        if (docuninja) await expect(page.locator('#docuninja-container')).toBeVisible();
    });
}

for (const mode of ['none', 'partial', 'matomo', 'google', 'global', 'both', 'hosted', 'hosted-none']) {
    test(`analytics consent gates ${mode} configuration and remembers rejection`, async ({ page }) => {
        const requests: string[] = [];
        await page.route(/https:\/\/(analytics\.test|www\.google-analytics\.com|www\.googletagmanager\.com)\//, route => {
            requests.push(route.request().url());
            return route.fulfill({ body: '', contentType: 'text/javascript' });
        });
        await mount(page, views[mode]);
        await page.addScriptTag({ content: `${analytics}\ninitAnalyticsConsent();` });
        if (['none', 'partial', 'hosted-none'].includes(mode)) {
            await expect(page.locator('[data-analytics-consent]')).toHaveCount(0);
            expect(requests).toEqual([]);
            return;
        }
        await expect(page.locator('[data-consent-notice]')).toBeVisible();
        expect(requests).toEqual([]);
        await page.locator('[data-consent-choice="rejected"]').click();
        await page.reload();
        await page.addScriptTag({ content: `${analytics}\ninitAnalyticsConsent();` });
        await expect(page.locator('[data-consent-notice]')).toBeHidden();
        expect(requests).toEqual([]);
        await page.locator('[data-consent-preferences]').click();
        await page.locator('[data-consent-choice="accepted"]').click();
        await expect.poll(() => requests.length).toBe(['both', 'hosted'].includes(mode) ? 2 : 1);
        await page.reload();
        await page.addScriptTag({ content: `${analytics}\ninitAnalyticsConsent();` });
        await expect(page.locator('[data-consent-notice]')).toBeHidden();
        await expect.poll(() => requests.length).toBe(['both', 'hosted'].includes(mode) ? 4 : 2);
        await page.locator('[data-consent-preferences]').click();
        await Promise.all([page.waitForEvent('load'), page.locator('[data-consent-choice="rejected"]').click()]);
        const count = requests.length;
        await page.addScriptTag({ content: `${analytics}\ninitAnalyticsConsent();` });
        await expect(page.locator('[data-consent-notice]')).toBeHidden();
        expect(requests.length).toBe(count);
    });
}

for (const choice of ['accepted', 'rejected']) {
    test(`analytics consent remembers ${choice} across layouts and beyond 180 days`, async ({ page }) => {
        await page.route(/https:\/\/(analytics\.test|www\.googletagmanager\.com)\//, route => route.fulfill({ body: '' }));
        await mount(page, views.matomo);
        await page.addScriptTag({ content: `${analytics}\ninitAnalyticsConsent();` });
        await page.locator(`[data-consent-choice="${choice}"]`).click();
        await page.locator('[data-analytics-consent]').evaluate((el, html) => { el.outerHTML = html; }, views['same-tenant-clean']);
        await page.clock.setFixedTime(new Date(Date.now() + 365 * 86400000));
        await page.addScriptTag({ content: `${analytics}\ninitAnalyticsConsent();` });
        await expect(page.locator('[data-consent-notice]')).toBeHidden();
        expect(await page.evaluate(() => Object.keys(localStorage).filter(key => key.startsWith('portal-analytics-v1:')).length)).toBe(1);
    });
}

for (const guard of ['contact', 'vendor']) {
    test(`Matomo attributes the ${guard} only after consent and before pageview`, async ({ page }) => {
        await page.route('https://analytics.test/**', route => route.fulfill({ body: '' }));
        await mount(page, views[`attributed-${guard}`]);
        await page.addScriptTag({ content: `${analytics}\ninitAnalyticsConsent();` });
        expect(await page.evaluate(() => (window as any)._paq)).toBeUndefined();
        await page.locator('[data-consent-choice="accepted"]').click();
        const queue = await page.evaluate(() => (window as any)._paq);
        expect(queue).toContainEqual(['setUserId', guard === 'contact' ? 'Test Contact' : 'Test Vendor']);
        expect(queue.findIndex((entry: string[]) => entry[0] === 'setUserId')).toBeLessThan(queue.findIndex((entry: string[]) => entry[0] === 'trackPageView'));
    });
}

test('legacy dismissal and a different tenant do not grant consent', async ({ page }) => {
    await mount(page, views.matomo);
    await page.evaluate(() => { document.cookie = 'cookieconsent_status=dismiss'; });
    await page.addScriptTag({ content: `${analytics}\ninitAnalyticsConsent();` });
    await expect(page.locator('[data-consent-notice]')).toBeVisible();
    await page.locator('[data-consent-choice="rejected"]').click();
    await page.locator('[data-analytics-consent]').evaluate((el, html) => { el.outerHTML = html; }, views['other-tenant']);
    await page.evaluate(() => (window as any).initAnalyticsConsent());
    await expect(page.locator('[data-consent-notice]')).toBeVisible();
});

test('payment cancellation rebuilds the steps and submits the newly selected gateway once', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    const fields = ['company_gateway_id', 'payment_method_id', 'signature', 'contact_first_name', 'contact_last_name', 'contact_email', 'client_city', 'client_postal_code'];
    await mount(page, `<meta name="require-invoice-signature" content="1"><meta name="show-invoice-terms" content="1">
        <form id="payment-form">${fields.map(name => `<input type="hidden" name="${name}" value="">`).join('')}</form>
        <button class="dropdown-gateway-button" data-company-gateway-id="one" data-gateway-type-id="1" data-is-paypal="0">First gateway</button>
        <button class="dropdown-gateway-button" data-company-gateway-id="two" data-gateway-type-id="2" data-is-paypal="1">PayPal</button>
        <dialog id="displayRequiredFieldsModal"><button data-dialog-close>Close</button>
            <input name="rff_first_name" value="First"><input name="rff_last_name" value="Last"><input name="rff_email" value="client@example.test">
            <input name="rff_city" value="Sydney"><input name="rff_postal_code" value="2000"><button id="rff-next-step">Next</button>
        </dialog>${views.signature}${views.terms}`, 'resources/js/clients/invoices/payment.js');
    await page.evaluate(() => {
        (window as any).submissions = 0;
        (document.getElementById('payment-form') as HTMLFormElement).submit = () => { (window as any).submissions++; };
    });
    await page.getByRole('button', { name: 'First gateway' }).click();
    await draw(page);
    await page.locator('#signature-next-step').click();
    await page.locator('#close-terms-button').click();
    await page.getByRole('button', { name: 'PayPal', exact: true }).click();
    await expect(page.locator('#displayRequiredFieldsModal')).toBeVisible();
    await page.locator('#rff-next-step').click();
    await page.locator('#signature-next-step').click();
    await page.locator('#accept-terms-button').click();
    await expect(page.locator('[name="company_gateway_id"]')).toHaveValue('two');
    await expect(page.locator('[name="client_city"]')).toHaveValue('Sydney');
    expect(await page.evaluate(() => (window as any).submissions)).toBe(1);
});

test('Livewire signature initializes, preserves strokes and cleans up when removed', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width: 375, height: 667 });
    await mount(page, `<script>window.livewireScriptConfig = {csrf: 'test', uri: '/livewire/update', progressBar: true};</script>${views['livewire-signature']}`, 'resources/js/app.js');
    await expect(page.locator('#save-button')).toBeDisabled();
    const canvas = await page.locator('#signature-pad').boundingBox();
    await page.mouse.move(canvas!.x + 15, canvas!.y + 25);
    await page.mouse.down();
    await page.mouse.move(canvas!.x + 120, canvas!.y + 65, { steps: 10 });
    await page.mouse.up();
    await expect(page.locator('#save-button')).toBeEnabled();
    await page.setViewportSize({ width: 667, height: 375 });
    await expect(page.locator('#save-button')).toBeEnabled();
    await page.locator('#clear-signature').click();
    await expect(page.locator('#save-button')).toBeDisabled();
    await page.locator('[x-data="portalSignature"]').evaluate(el => el.remove());
    await page.setViewportSize({ width: 375, height: 667 });
    expect(errors).toEqual([]);
});

test('teleported dialogs initialise after insertion and release scroll locking on removal', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await mount(page, `<script>window.livewireScriptConfig = {csrf: 'test', uri: '/livewire/update', progressBar: true};</script>
        <div x-data="{ visible: false }" style="position:relative;z-index:0;overflow:hidden;height:80px">
            <button id="insert" @click="visible = true">Insert</button>
            <template x-if="visible"><div x-data="portalDialog">
                <button id="open-dialog" @click="open()">Open</button>
                <template x-teleport="body"><dialog x-ref="dialog" id="dynamic-dialog" class="portal-dialog" data-error-message="Try again">
                    <div class="portal-dialog-body"><input aria-label="Example"><button @click="visible = false">Remove</button></div>
                </dialog></template>
            </div></template>
        </div>`, 'resources/js/app.js');
    for (let attempt = 0; attempt < 2; attempt++) {
        await page.locator('#insert').click();
        await page.locator('#open-dialog').click();
        await expect(page.locator('body > #dynamic-dialog')).toBeVisible();
        await expect(page.locator('html')).toHaveClass(/portal-dialog-open/);
        await page.locator('#dynamic-dialog').getByRole('button', { name: 'Remove' }).click();
        await expect(page.locator('#dynamic-dialog')).toHaveCount(0);
        await expect(page.locator('html')).not.toHaveClass(/portal-dialog-open/);
        await expect(page.locator('.backdrop')).toHaveCount(0);
    }
    expect(errors).toEqual([]);
});

test('blocked storage does not prevent rejecting analytics or using the page', async ({ page }) => {
    await mount(page, views.matomo);
    await page.evaluate(() => {
        Object.defineProperty(window, 'localStorage', { get() { throw new Error('Storage blocked'); } });
    });
    await page.addScriptTag({ content: `${analytics}\ninitAnalyticsConsent();` });
    await page.locator('[data-consent-choice="rejected"]').click();
    await expect(page.locator('[data-consent-notice]')).toBeHidden();
    await expect(page.locator('[data-consent-preferences]')).toBeVisible();
});

async function mountInvoiceSummary(page: Page) {
    await mount(page, `<script>window.livewireScriptConfig = {csrf: 'test', uri: '/livewire/update', progressBar: true};</script>
        <div class="grid grid-cols-1 md:grid-cols-2"><div class="min-w-0 p-2">${views.summary}</div><button id="payment-step">Payment method</button></div>`, 'resources/js/app.js');
    await expect(page.locator('.portal-summary')).toBeVisible();
}

async function expectSummaryFits(page: Page) {
    await expect.poll(() => page.evaluate(() => {
        const root = document.documentElement;
        return root.scrollWidth <= root.clientWidth;
    })).toBe(true);
}

for (const width of [320, 390]) {
    test(`mobile invoice summary stays compact and wraps long values at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 568 });
        await mountInvoiceSummary(page);
        await page.addStyleTag({ content: 'html { scrollbar-gutter: stable; }' });
        await expect(page.locator('details')).not.toHaveAttribute('open');
        const step = await page.locator('#payment-step').boundingBox();
        expect(step!.y + step!.height).toBeLessThan(568);
        await page.locator('summary').click();
        await expect(page.locator('details')).toHaveAttribute('open');
        await expect(page.getByText('INV-123', { exact: false })).toHaveCount(3);
        await expectSummaryFits(page);
        // Native summary keyboard activation must still toggle the mobile card.
        await page.locator('summary').focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('details')).not.toHaveAttribute('open');
    });
}

for (const width of [1024, 1440]) {
    test(`desktop invoice summary keeps the original visible card at ${width}px`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 1000 });
        await mountInvoiceSummary(page);
        await expect(page.locator('details')).toHaveAttribute('open');
        await expect(page.locator('summary')).toBeHidden();
        await expect(page.getByRole('heading', { name: 'Invoices', exact: true })).toBeVisible();
        await expect(page.getByText('INV-123', { exact: false })).toHaveCount(3);
        await expect(page.locator('dd').filter({ hasText: '$3.00' })).toBeVisible();
        await expect(page.locator('dd').filter({ hasText: '$300.00' })).toBeVisible();
        await expect(page.locator('button[wire\\:click^="downloadDocument"]')).toHaveCount(3);
        const row = page.locator('dt').filter({ hasText: 'Invoice Date' }).first().locator('..');
        await expect(row).toHaveCSS('flex-direction', 'row');
        await expect(row).toHaveCSS('align-items', 'center');
        await expectSummaryFits(page);
        const card = await page.getByRole('heading', { name: 'Invoices', exact: true }).boundingBox();
        expect(card!.y).toBeLessThan(50); // No extra accordion header above the original card.
        await page.screenshot({ path: testInfo.outputPath(`invoice-summary-${width}.png`), fullPage: true });
    });
}

test('invoice summary follows the desktop breakpoint and preserves mobile expansion', async ({ page }) => {
    await page.setViewportSize({ width: 767, height: 900 });
    await mountInvoiceSummary(page);
    const details = page.locator('details');
    const toggle = page.locator('summary');
    await expect(details).not.toHaveAttribute('open');
    await page.setViewportSize({ width: 768, height: 900 });
    await expect(details).toHaveAttribute('open');
    await expect(toggle).toBeHidden();
    await page.setViewportSize({ width: 767, height: 900 });
    await expect(toggle).toBeVisible();
    await expect(details).not.toHaveAttribute('open');
    await toggle.click();
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(details).toHaveAttribute('open');
    await expect(toggle).toBeHidden();
    await page.setViewportSize({ width: 390, height: 900 });
    await expect(details).toHaveAttribute('open');
    await expect(toggle).toBeVisible();
    await expectSummaryFits(page);
    await toggle.click();
    await expect(details).not.toHaveAttribute('open');
});
