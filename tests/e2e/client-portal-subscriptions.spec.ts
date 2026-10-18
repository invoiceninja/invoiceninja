import {
    createAndLogInClient,
    dismissCookieConsent,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createPortalGroupSetting,
    createPortalSubscription,
    createRecurringInvoiceWithSubscription,
} from './portal-entity-helpers';

test.describe('Client portal subscriptions', () => {
    test('lists a created subscription purchase entry point', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page);
        const { subscription } = await createPortalSubscription(api, {
            name: uniqueName('list-sub'),
        });

        await page.goto('/client/subscriptions');
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'Subscriptions',
        );
        await expect(page.locator('main')).toBeVisible();

        const purchaseResponse = await page.goto(
            `/client/subscriptions/${subscription.id}/purchase`,
        );
        expect(purchaseResponse?.ok()).toBe(true);
        await expect(page.locator('main, body')).toBeVisible();
        await expect(page).toHaveURL(
            new RegExp(`/client/subscriptions/${subscription.id}/purchase`),
        );
    });

    test('opens the v2 purchase flow for a subscription', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page);
        const { subscription } = await createPortalSubscription(api, {
            name: uniqueName('v2-sub'),
        });

        const response = await page.goto(
            `/client/subscriptions/${subscription.id}/purchase/v2`,
        );
        expect(response?.ok()).toBe(true);
        await expect(page).toHaveURL(
            new RegExp(
                `/client/subscriptions/${subscription.id}/purchase/v2`,
            ),
        );
        await expect(page.locator('main, body')).toBeVisible();
    });

    test('shows plan switch links when plan changes are allowed', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const group = await createPortalGroupSetting(
            api,
            uniqueName('plan-group'),
        );
        const { subscription: current } = await createPortalSubscription(api, {
            name: uniqueName('current-plan'),
            allowPlanChanges: true,
            groupId: group.id,
        });
        const { subscription: target } = await createPortalSubscription(api, {
            name: uniqueName('target-plan'),
            allowPlanChanges: true,
            cost: 40,
            groupId: group.id,
        });
        const recurring = await createRecurringInvoiceWithSubscription(
            api,
            client,
            current,
        );

        await page.goto(`/client/recurring_invoices/${recurring.id}`);
        await dismissCookieConsent(page);

        await expect(
            page.getByRole('heading', { name: /Switch Plans/i }),
        ).toBeVisible();

        const planSwitch = page.locator(
            `a[href*="/plan_switch/"][href*="${target.id}"]`,
        );
        await expect(planSwitch.first()).toBeVisible();
        await planSwitch.first().click();
        await expect(page).toHaveURL(
            new RegExp(
                `/client/subscriptions/${recurring.id}/plan_switch/${target.id}`,
            ),
        );
        await expect(page.getByRole('main')).toBeVisible();
    });
});
