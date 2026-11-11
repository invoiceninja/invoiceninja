import { type Locator, type Page } from '@playwright/test';
import { createAndLogInClient, clearPortalOverlays, selectEntityTableRow, waitForAlpine } from './client-portal-helpers';
import { createSentQuote, createSentInvoice, createSentCredit, createRecurringInvoice, createPortalPayment } from './portal-entity-helpers';
import { expect, test, uniqueName } from './fixtures';

async function settle(control: Locator): Promise<void> {
    await control.evaluate(async (el) => {
        await Promise.all(el.getAnimations().filter((animation) => animation instanceof CSSTransition)
            .map((animation) => animation.finished.catch(() => undefined)));
    });
}

async function geometry(control: Locator) {
    return control.evaluate((el) => {
        const r = el.getBoundingClientRect(), s = getComputedStyle(el);
        // The portal scrolls <main> independently; keyboard focus may scroll it.
        let x = r.x + scrollX, y = r.y + scrollY;
        for (let parent = el.parentElement; parent; parent = parent.parentElement) {
            if (parent !== document.scrollingElement) {
                x += parent.scrollLeft;
                y += parent.scrollTop;
            }
        }
        return { x, y, width: r.width, height: r.height,
            weight: s.fontWeight, size: s.fontSize, spacing: s.letterSpacing };
    });
}

async function expectStableControl(page: Page, control: Locator): Promise<void> {
    await control.scrollIntoViewIfNeeded();
    await page.mouse.move(0, 0);
    await control.evaluate((el) => (el as HTMLElement).blur());
    await settle(control);
    const before = await geometry(control);
    const description = await control.evaluate((el) => `${el.tagName}#${el.id} ${el.textContent?.trim().slice(0, 60)}`);
    await control.hover();
    await settle(control);
    expect(await geometry(control), `${description} hover`).toEqual(before);
    if (await control.isEnabled()) {
        await page.keyboard.press('Tab');
        await control.focus();
        await settle(control);
        expect(await geometry(control), `${description} focus`).toEqual(before);
        const focusVisible = await control.evaluate((el) => {
            const s = getComputedStyle(el);
            return s.boxShadow !== 'none' || (s.outlineStyle !== 'none' && s.outlineWidth !== '0px') || s.textDecorationLine.includes('underline');
        });
        expect(focusVisible, `${description} keyboard focus`).toBe(true);
        await control.evaluate((el) => (el as HTMLElement).blur());
    }
}

for (const [width, color] of [[1280, '#1c64f2'], [390, '#008060']] as const) {
    test(`quote actions and client sidebar stay stable at ${width}px`, async ({ api, page }) => {
        await page.setViewportSize({ width, height: 1000 });
        const client = await createAndLogInClient(api, page, { settings: { primary_color: color } });
        const quote = await createSentQuote(api, client, { label: uniqueName('button-hover'), cost: 55 });
        await page.goto(`/client/quotes/${quote.id}`);
        await clearPortalOverlays(page);
        const approve = page.locator('#approve-button');
        const reject = page.locator('#reject-button');
        await expect(approve).toBeVisible();
        await expectStableControl(page, approve);
        await expectStableControl(page, reject);
        await approve.hover();
        expect(await approve.evaluate((el) => getComputedStyle(el).backgroundImage)).toContain('linear-gradient');
        expect(await approve.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(width === 1280 ? 'rgb(28, 100, 242)' : 'rgb(0, 128, 96)');

        await reject.click();
        const modal = page.locator('#displayRejectModal');
        await expect(modal).toBeVisible();
        for (const button of await modal.locator('button:visible').all()) await expectStableControl(page, button);
        await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
        await expect(modal).toBeHidden();

        await waitForAlpine(page);
        if (width < 768) await page.locator('button').filter({ has: page.locator('svg path[d="M4 6h16M4 12h16M4 18h7"]') }).click();
        const navigation = page.locator('nav a[id]:visible');
        await expect(navigation.first()).toBeVisible();
        for (const link of await navigation.all()) await expectStableControl(page, link);
        const selected = page.locator('nav a#quotes:visible');
        await expect(selected).toHaveClass(/bg-primary/);
        if (width < 768) await page.keyboard.press('Escape');
    });
}

test('all sidebar pages and profile expose stable portal controls', async ({ api, page }, testInfo) => {
    test.setTimeout(180_000);
    const client = await createAndLogInClient(api, page);
    const paths = await page.locator('nav a[id]:visible').evaluateAll((links) => links.map((link) => (link as HTMLAnchorElement).pathname));
    expect(paths).toHaveLength(13);
    paths.push(`/client/profile/${client.contacts[0].id}/edit`);
    const invoice = await createSentInvoice(api, client);
    const credit = await createSentCredit(api, client);
    const recurring = await createRecurringInvoice(api, client);
    const payment = await createPortalPayment(api, client);
    const project = await api.createEntity('projects', {
        client_id: client.id,
        name: uniqueName('button-project'),
        public_notes: 'Long public notes to exercise the expand button. '.repeat(40),
    });
    paths.push(`/client/invoices/${invoice.id}`, `/client/credits/${credit.id}`,
        `/client/recurring_invoices/${recurring.id}`, `/client/payments/${payment.id}`,
        `/client/projects/${project.id}`);
    const checked: Record<string, number> = {};
    for (const path of paths) {
        await test.step(path, async () => {
            const response = await page.goto(path);
            expect(response?.ok()).toBe(true);
            await clearPortalOverlays(page);
            await waitForAlpine(page);
            const controls = page.locator('main button:visible, main a.button:visible, main a.button-link:visible, main input[type="submit"]:visible');
            checked[path] = await controls.count();
            for (const control of await controls.all()) await expectStableControl(page, control);
        });
    }
    expect(Object.values(checked).reduce((sum, count) => sum + count, 0)).toBeGreaterThan(0);
    await testInfo.attach('rendered-control-coverage', { body: JSON.stringify(checked, null, 2), contentType: 'application/json' });
});

test('quote bulk buttons keep disabled and enabled hover states distinct', async ({ api, page }) => {
    const client = await createAndLogInClient(api, page);
    const quote = await createSentQuote(api, client, { label: uniqueName('button-disabled'), cost: 55 });
    await page.goto('/client/quotes');
    await clearPortalOverlays(page);
    const actions = page.locator('button[name="action"]');
    await expect(actions).toHaveCount(3);
    for (const action of await actions.all()) {
        await expect(action).toBeDisabled();
        await page.mouse.move(0, 0);
        const before = await action.evaluate((el) => { const s = getComputedStyle(el); return [s.backgroundColor, s.backgroundImage]; });
        await action.hover({ force: true });
        await settle(action);
        expect(await action.evaluate((el) => { const s = getComputedStyle(el); return [s.backgroundColor, s.backgroundImage]; })).toEqual(before);
    }
    await selectEntityTableRow(page, '.quotes-table', quote.number ?? '');
    for (const action of await actions.all()) {
        await expect(action).toBeEnabled();
        await expectStableControl(page, action);
    }
});

test('login and password recovery actions stay stable', async ({ page }) => {
    for (const path of ['/client/login', '/client/password/reset']) {
        await page.goto(path);
        await clearPortalOverlays(page);
        const buttons = page.locator('button.button:visible');
        expect(await buttons.count()).toBeGreaterThan(0);
        for (const button of await buttons.all()) await expectStableControl(page, button);
    }
});
