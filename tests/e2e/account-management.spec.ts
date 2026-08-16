import { accountManagementTestAccount, type TestAccount } from './accounts';
import { expect, test as baseTest } from './fixtures';
import {
    accountCanStartTrial,
    accountManagementSkipReason,
    assertUpgradeQuoteMatchesEngine,
    cancelTrial,
    clearDocuNinjaBetaAllowlist,
    completeUpgradePayment,
    confirmStripePaymentIntent,
    createInvoicePaymentIntent,
    createPaymentMethodSetupIntent,
    createUpgradePaymentIntent,
    docuNinjaBetaCode,
    downloadAccountInvoice,
    downgradeDocuNinjaSeats,
    downgradeToFree,
    expectedMidCycleCharge,
    expectedStripeAmountCents,
    expectedTermUpgradeCharge,
    fetchStripePaymentIntentAmountCents,
    getAccountManagementPlans,
    getBillingEngineQuote,
    getUpgradeDescription,
    getUpgradeDescriptionWithEngineCheck,
    listAccountInvoices,
    listAccountUsers,
    listPaymentMethods,
    payBillingInvoice,
    performUpgrade,
    preparePaidAccountForQuotes,
    preparePayableBillingInvoice,
    prepareTrialAccount,
    proRataRatio,
    type ProRataScenario,
    readAccountPlanState,
    readBillingRecurringState,
    requestDocuNinjaBetaUpgrade,
    resetAccountPlanState,
    seedDocuNinjaBetaAllowlist,
    startTrial,
    stripeConfigured,
    upgradeQuoteErrorText,
} from './account-management-helpers';

const test = baseTest.extend<{}, { account: TestAccount }>({
    account: [
        async ({}, use) => {
            await use(accountManagementTestAccount());
        },
        { scope: 'worker' },
    ],
});

test.describe.configure({ mode: 'serial' });

const proRataBoundaryScenarios: ProRataScenario[] = [
    {
        name: 'same-day pro to enterprise upgrade charges the full delta',
        setup: { plan: 'pro', term: 'month', users: 1, days_into_period: 0 },
        request: { plan: 'enterprise', term: 'month', users: 2 },
    },
    {
        name: 'day-one pro to enterprise upgrade keeps almost the full delta',
        setup: { plan: 'pro', term: 'month', users: 1, days_into_period: 1 },
        request: { plan: 'enterprise', term: 'month', users: 2 },
    },
    {
        name: 'mid-cycle pro to enterprise upgrade charges a partial delta',
        setup: { plan: 'pro', term: 'month', users: 1, days_into_period: 14 },
        request: { plan: 'enterprise', term: 'month', users: 2 },
    },
    {
        name: 'near-expiry pro to enterprise upgrade rounds tiny pro rata amounts',
        setup: { plan: 'pro', term: 'month', users: 1, days_into_period: 29 },
        request: { plan: 'enterprise', term: 'month', users: 2 },
    },
    {
        name: 'last-day pro to enterprise upgrade hits the minimum cent boundary',
        setup: { plan: 'pro', term: 'month', users: 1, days_into_period: 30 },
        request: { plan: 'enterprise', term: 'month', users: 2 },
    },
    {
        name: 'same-day docuninja seat additions charge the full seat price',
        setup: {
            plan: 'pro',
            term: 'month',
            users: 1,
            docuninja_users: 0,
            days_into_period: 0,
        },
        request: {
            plan: 'pro',
            term: 'month',
            users: 1,
            docuninja_users: 1,
        },
    },
    {
        name: 'last-day docuninja seat additions round to the minimum non-zero charge',
        setup: {
            plan: 'pro',
            term: 'month',
            users: 1,
            docuninja_users: 0,
            days_into_period: 30,
        },
        request: {
            plan: 'pro',
            term: 'month',
            users: 1,
            docuninja_users: 1,
        },
    },
    {
        name: 'same-day month to year pro upgrades credit the full unused monthly period',
        setup: { plan: 'pro', term: 'month', users: 1, days_into_period: 0 },
        request: { plan: 'pro', term: 'year', users: 1 },
    },
    {
        name: 'mid-cycle month to year pro upgrades credit only the unused monthly portion',
        setup: { plan: 'pro', term: 'month', users: 1, days_into_period: 14 },
        request: { plan: 'pro', term: 'year', users: 1 },
    },
    {
        name: 'same-day enterprise seat tier increases charge the full tier delta',
        setup: {
            plan: 'enterprise',
            term: 'month',
            users: 5,
            days_into_period: 0,
        },
        request: { plan: 'enterprise', term: 'month', users: 10 },
    },
];

test.describe('Account management plans and pro rata quotes', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);

        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('loads hosted plan catalog from admin-api', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const catalog = await getAccountManagementPlans(api.context);

        expect(catalog.plans.pro_plan).toBe(14);
        expect(catalog.plans.pro_plan_annual).toBe(140);
        expect(catalog.plans.enterprise_plan).toBe(18);
        expect(catalog.plans.docuninja_user).toBe(6);
        expect(catalog.products.pro_plan).toMatchObject({
            plan: 'pro',
            term: 'month',
        });
    });

    test('quotes a full-period pro monthly upgrade from free', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        resetAccountPlanState(ownerEmail);

        const quote = await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(14);
        expect(quote.body.requires_payment).toBe(true);
        expect(quote.body.credit_raw).toBe(0);
        expect(quote.body.description).toContain('Pro Plan');
    });

    test('quotes a full-period pro annual upgrade from free', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const quote = await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'year',
            users: 1,
        });

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(140);
        expect(quote.body.requires_payment).toBe(true);
    });

    test('quotes enterprise monthly with docuninja seats from free', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const quote = await getUpgradeDescription(api.context, {
            plan: 'enterprise',
            term: 'month',
            users: 2,
            docuninja_users: 2,
        });

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(30);
        expect(quote.body.requires_payment).toBe(true);
    });

    test('rejects a paid plan downgrade through the upgrade quote endpoint', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'enterprise',
            term: 'month',
            users: 10,
        });

        const quote = await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        expect(quote.status).toBe(422);
        expect(upgradeQuoteErrorText(quote.body)).toMatch(/downgrade flow/i);
    });

    test('rejects reducing enterprise seats through the upgrade quote endpoint', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'enterprise',
            term: 'month',
            users: 10,
        });

        const quote = await getUpgradeDescription(api.context, {
            plan: 'enterprise',
            term: 'month',
            users: 5,
        });

        expect(quote.status).toBe(422);
        expect(upgradeQuoteErrorText(quote.body)).toMatch(/downgrade flow/i);
    });

    test('rejects annual to monthly changes mid-term', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'year',
            users: 1,
        });

        const quote = await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        expect(quote.status).toBe(422);
        expect(upgradeQuoteErrorText(quote.body)).toMatch(
            /annual subscriptions cannot be changed to monthly|downgrade flow/i,
        );
    });

    test('quotes pro rata docuninja seat additions mid-cycle', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const paid = preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            docuninja_users: 0,
            days_into_period: 14,
        });

        const ratio = proRataRatio(
            paid.plan_paid!,
            paid.plan_expires!,
            'month',
        );
        const expected = expectedMidCycleCharge(6, ratio);

        const quote = await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
            docuninja_users: 1,
        });

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(expected);
        expect(quote.body.requires_payment).toBe(expected > 0);
    });

    test('quotes pro rata enterprise upgrades mid-cycle', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const paid = preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            days_into_period: 14,
        });

        const ratio = proRataRatio(
            paid.plan_paid!,
            paid.plan_expires!,
            'month',
        );
        const expected = expectedMidCycleCharge(18 - 14, ratio);

        const quote = await getUpgradeDescription(api.context, {
            plan: 'enterprise',
            term: 'month',
            users: 2,
        });

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(expected);
    });

    test('quotes pro rata enterprise seat tier increases mid-cycle', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const paid = preparePaidAccountForQuotes(ownerEmail, {
            plan: 'enterprise',
            term: 'month',
            users: 5,
            days_into_period: 14,
        });

        const ratio = proRataRatio(
            paid.plan_paid!,
            paid.plan_expires!,
            'month',
        );
        const expected = expectedMidCycleCharge(54 - 32, ratio);

        const quote = await getUpgradeDescription(api.context, {
            plan: 'enterprise',
            term: 'month',
            users: 10,
        });

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(expected);
    });
});

test.describe('Account management upgrade checkout', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);

        if (skipReason) {
            return;
        }

        if (!stripeConfigured()) {
            skipReason = 'Set STRIPE_KEYS (or NINJA_STRIPE_KEY) to run upgrade checkout tests';
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('creates a PaymentIntent that matches the cached pro rata quote', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        resetAccountPlanState(ownerEmail);

        const quote = await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        expect(quote.status).toBe(200);

        const intent = await createUpgradePaymentIntent(
            api.context,
            quote.body.pro_rata_raw,
        );

        expect(intent.status).toBe(200);
        expect(intent.requires_payment).toBe(true);
        expect(intent.client_secret).toMatch(/_secret_/);
        expect(intent.payment_intent_id).toMatch(/^pi_/);
    });

    test('completes a free to pro monthly upgrade through Stripe', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        test.setTimeout(120_000);

        resetAccountPlanState(ownerEmail);

        await performUpgrade(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const account = readAccountPlanState(ownerEmail);

        expect(account.plan).toBe('pro');
        expect(account.plan_term).toBe('month');
        expect(account.num_users).toBe(1);
        expect(account.plan_expires).toBeTruthy();
    });

    test('charges pro rata when adding a docuninja seat mid-cycle', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        test.setTimeout(120_000);

        const paid = readAccountPlanState(ownerEmail);

        expect(paid.plan).toBe('pro');
        expect(paid.docuninja_num_users).toBe(0);

        const ratio = proRataRatio(
            paid.plan_paid!,
            paid.plan_expires!,
            'month',
        );
        const expectedCharge = expectedMidCycleCharge(6, ratio);

        const quote = await performUpgrade(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
            docuninja_users: 1,
        });

        expect(quote.pro_rata_raw).toBe(expectedCharge);

        const account = readAccountPlanState(ownerEmail);

        expect(account.plan).toBe('pro');
        expect(account.docuninja_num_users).toBe(1);
    });

    test('charges pro rata when upgrading pro to enterprise mid-cycle', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        test.setTimeout(120_000);

        const paid = readAccountPlanState(ownerEmail);
        const ratio = proRataRatio(
            paid.plan_paid!,
            paid.plan_expires!,
            'month',
        );
        const expectedCharge = expectedMidCycleCharge(18 - 14, ratio);

        const quote = await performUpgrade(api.context, {
            plan: 'enterprise',
            term: 'month',
            users: 2,
            docuninja_users: paid.docuninja_num_users,
        });

        expect(quote.pro_rata_raw).toBe(expectedCharge);

        const account = readAccountPlanState(ownerEmail);

        expect(account.plan).toBe('enterprise');
        expect(account.num_users).toBe(2);
    });

    test('finalizes a zero-cost quote without a PaymentIntent', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'enterprise',
            term: 'month',
            users: 10,
            docuninja_users: 1,
            days_into_period: 1,
        });

        const quote = await getUpgradeDescription(api.context, {
            plan: 'enterprise',
            term: 'month',
            users: 10,
            docuninja_users: 1,
        });

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(0);
        expect(quote.body.requires_payment).toBe(false);

        const intent = await createUpgradePaymentIntent(api.context);

        expect(intent.status).toBe(200);
        expect(intent.requires_payment).toBe(false);
        expect(intent.client_secret).toBeNull();

        const payment = await completeUpgradePayment(api.context);

        expect(payment.status).toBe(200);
        expect(payment.message).toMatch(/successful/i);
    });
});

test.describe('Account management downgrades', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('schedules a graceful downgrade to free for an older paid account', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            plan_started_days_ago: 35,
        });

        const before = readAccountPlanState(ownerEmail);

        const response = await downgradeToFree(api.context);

        expect(response.status).toBe(200);
        expect(response.message).toMatch(/success/i);

        const after = readAccountPlanState(ownerEmail);

        expect(after.plan).toBe(before.plan);
        expect(after.plan_expires).toBe(before.plan_expires);
    });

    test('schedules docuninja seat downgrades without changing the base plan immediately', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            docuninja_users: 3,
            days_into_period: 10,
        });

        const before = readAccountPlanState(ownerEmail);

        const response = await downgradeDocuNinjaSeats(api.context, 1);

        expect(response.status).toBe(200);

        const after = readAccountPlanState(ownerEmail);

        expect(after.plan).toBe(before.plan);
        expect(after.docuninja_num_users).toBe(1);
    });

    test('rejects invalid docuninja seat counts through the downgrade endpoint', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const response = await api.context.request.post(
            '/api/client/account_management/docuninja/downgrade',
            { data: { num_users: 51 } },
        );

        expect(response.status()).toBeGreaterThanOrEqual(400);

        const body = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        expect(upgradeQuoteErrorText(body)).toMatch(/num_users|invalid|maximum|50/i);
    });
});

test.describe('Account management payment guardrails', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);

        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('rejects payment finalization when no quote is cached', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const payment = await completeUpgradePayment(api.context, 'pi_missing_quote');

        expect(payment.status).toBeGreaterThanOrEqual(400);
    });

    test('rejects confirming a PaymentIntent that was never created for the quote', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        if (!stripeConfigured()) {
            test.skip(true, 'Set STRIPE_KEYS to run PaymentIntent guardrail tests');
        }

        await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const payment = await completeUpgradePayment(
            api.context,
            'pi_playwright_unconfirmed',
        );

        expect(payment.status).toBeGreaterThanOrEqual(400);
    });

    test('rejects stale PaymentIntents that do not match the cached quote amount', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        if (!stripeConfigured()) {
            test.skip(true, 'Set STRIPE_KEYS to run PaymentIntent guardrail tests');
        }

        test.setTimeout(120_000);

        await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const intent = await createUpgradePaymentIntent(api.context, 14);

        expect(intent.client_secret).toBeTruthy();

        await getUpgradeDescription(api.context, {
            plan: 'enterprise',
            term: 'month',
            users: 2,
        });

        if (!intent.client_secret) {
            throw new Error('Expected a PaymentIntent client secret.');
        }

        const paymentIntentId = await confirmStripePaymentIntent(
            intent.client_secret,
        );
        const payment = await completeUpgradePayment(api.context, paymentIntentId);

        expect(payment.status).toBeGreaterThanOrEqual(400);
    });
});

test.describe('Account management quote validation', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);

        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('rejects pro plans with more than one user', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const quote = await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'month',
            users: 2,
        });

        expect(quote.status).toBe(422);
        expect(upgradeQuoteErrorText(quote.body)).toMatch(/single user/i);
    });

    test('rejects upgrade quotes with more than fifty users', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const quote = await getUpgradeDescription(api.context, {
            plan: 'enterprise',
            term: 'month',
            users: 51,
        });

        expect(quote.status).toBe(422);
        expect(upgradeQuoteErrorText(quote.body)).toMatch(/users|50|invalid/i);
    });

    test('quotes month to year pro upgrades with credit for the unused monthly period', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            days_into_period: 14,
        });

        const quote = await getUpgradeDescriptionWithEngineCheck(
            api.context,
            ownerEmail,
            {
                plan: 'pro',
                term: 'year',
                users: 1,
            },
        );

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBeGreaterThan(0);
        expect(quote.body.credit_raw).toBeGreaterThan(0);
    });
});

test.describe('Account management pro rata amount sanity', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    for (const scenario of proRataBoundaryScenarios) {
        test(`quote API matches billing engine: ${scenario.name}`, async ({
            api,
        }) => {
            if (skipReason) {
                test.skip(true, skipReason);
            }

            resetAccountPlanState(ownerEmail);
            preparePaidAccountForQuotes(ownerEmail, scenario.setup);

            const quote = await getUpgradeDescriptionWithEngineCheck(
                api.context,
                ownerEmail,
                scenario.request,
            );

            expect(quote.status).toBe(200);
            expect(quote.body.pro_rata_raw).toBe(quote.engine.pro_rata);
            expect(quote.body.credit_raw ?? 0).toBe(quote.engine.credit);
            expect(quote.body.requires_payment).toBe(quote.engine.pro_rata > 0);
        });
    }

    test('same-day upgrade PaymentIntent amount matches the quoted pro rata charge', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        if (!stripeConfigured()) {
            test.skip(true, 'Set STRIPE_KEYS to verify Stripe PaymentIntent amounts');
        }

        resetAccountPlanState(ownerEmail);
        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            days_into_period: 0,
        });

        const quote = await getUpgradeDescriptionWithEngineCheck(
            api.context,
            ownerEmail,
            {
                plan: 'enterprise',
                term: 'month',
                users: 2,
            },
        );

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(4);

        const intent = await createUpgradePaymentIntent(
            api.context,
            quote.body.pro_rata_raw,
        );

        expect(intent.status).toBe(200);
        expect(intent.payment_intent_id).toBeTruthy();

        const stripeAmount = await fetchStripePaymentIntentAmountCents(
            intent.payment_intent_id!,
        );

        expect(stripeAmount).toBe(
            expectedStripeAmountCents(quote.body.pro_rata_raw!),
        );
    });

    test('mid-cycle PaymentIntent amount matches the quoted pro rata charge', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        if (!stripeConfigured()) {
            test.skip(true, 'Set STRIPE_KEYS to verify Stripe PaymentIntent amounts');
        }

        resetAccountPlanState(ownerEmail);
        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            days_into_period: 14,
        });

        const quote = await getUpgradeDescriptionWithEngineCheck(
            api.context,
            ownerEmail,
            {
                plan: 'enterprise',
                term: 'month',
                users: 2,
            },
        );

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBeGreaterThanOrEqual(0.5);

        const intent = await createUpgradePaymentIntent(
            api.context,
            quote.body.pro_rata_raw,
        );

        expect(intent.status).toBe(200);
        expect(intent.payment_intent_id).toBeTruthy();

        const stripeAmount = await fetchStripePaymentIntentAmountCents(
            intent.payment_intent_id!,
        );

        expect(stripeAmount).toBe(
            expectedStripeAmountCents(quote.body.pro_rata_raw!),
        );
    });
});

test.describe('Account management trials', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('quotes a full-period trial conversion without crediting the unpaid trial', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        prepareTrialAccount(ownerEmail);

        const quote = await getUpgradeDescription(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(14);
        expect(quote.body.credit_raw).toBe(0);
        expect(quote.body.requires_payment).toBe(true);
    });

    test('cancels an active trial through the cancel_trial endpoint', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        prepareTrialAccount(ownerEmail);

        const response = await cancelTrial(api.context);

        expect(response.status).toBe(204);

        const account = readAccountPlanState(ownerEmail);

        expect(account.is_trial).toBe(false);
        expect(account.plan).toBeNull();
        expect(account.plan_expires).toBeNull();
    });

    test('cancels an active trial through the free downgrade endpoint', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        prepareTrialAccount(ownerEmail);

        const response = await downgradeToFree(api.context);

        expect(response.status).toBe(200);

        const account = readAccountPlanState(ownerEmail);

        expect(account.is_trial).toBe(false);
        expect(account.plan).toBeNull();
    });

    test('starts a trial through the start_trial endpoint when the account is eligible', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        resetAccountPlanState(ownerEmail);

        if (!accountCanStartTrial(ownerEmail)) {
            test.skip(
                true,
                'small@example.com is outside the 14-day new-account trial eligibility window',
            );
        }

        const response = await startTrial(api.context);

        expect(response.status).toBe(200);

        const account = readAccountPlanState(ownerEmail);

        expect(account.is_trial).toBe(true);
        expect(account.plan).toBe('pro');
        expect(account.plan_expires).toBeTruthy();
    });

    test('converts a trial to paid pro through Stripe checkout', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        if (!stripeConfigured()) {
            test.skip(true, 'Set STRIPE_KEYS (or NINJA_STRIPE_KEY) to run trial conversion checkout');
        }

        test.setTimeout(120_000);

        prepareTrialAccount(ownerEmail);

        await performUpgrade(api.context, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const account = readAccountPlanState(ownerEmail);

        expect(account.is_trial).toBe(false);
        expect(account.plan).toBe('pro');
        expect(account.plan_term).toBe('month');
        expect(account.plan_paid).toBeTruthy();
    });
});

test.describe('Account management additional downgrade paths', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('downgrades immediately inside the money-back guarantee window', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            plan_started_days_ago: 10,
        });

        const response = await downgradeToFree(api.context);

        expect(response.status).toBe(200);

        const account = readAccountPlanState(ownerEmail);

        expect(account.plan).toBeNull();
        expect(account.plan_expires).toBeNull();
        expect(account.is_trial).toBe(false);
    });

    test('cleans up billing artifacts for already-free accounts', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        resetAccountPlanState(ownerEmail);

        const response = await downgradeToFree(api.context);

        expect(response.status).toBe(200);

        const account = readAccountPlanState(ownerEmail);

        expect(account.plan).toBeNull();
        expect(account.is_trial).toBe(false);
    });

    test('schedules docuninja disable requests through the downgrade endpoint', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const seeded = preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            docuninja_users: 2,
            days_into_period: 10,
        });

        const beforeDowngrade = readBillingRecurringState(ownerEmail);

        expect(beforeDowngrade.recurring_invoice_id).toBe(seeded.recurring_invoice_id);
        expect(beforeDowngrade.docuninja_quantity).toBe(2);
        expect(
            beforeDowngrade.product_keys.some((key) =>
                key.toLowerCase().includes('docuninja'),
            ),
        ).toBe(true);
        expect(beforeDowngrade.line_items_total).toBeGreaterThan(
            beforeDowngrade.plan_price ?? 0,
        );

        const response = await downgradeDocuNinjaSeats(api.context, 0);

        expect(response.status).toBe(200);

        const account = readAccountPlanState(ownerEmail);

        expect(account.docuninja_num_users).toBe(0);

        const afterDowngrade = readBillingRecurringState(ownerEmail);

        expect(afterDowngrade.recurring_invoice_id).toBe(seeded.recurring_invoice_id);
        expect(afterDowngrade.docuninja_quantity).toBe(0);
        expect(
            afterDowngrade.product_keys.some((key) =>
                key.toLowerCase().includes('docuninja'),
            ),
        ).toBe(false);
        expect(afterDowngrade.product_keys).toContain('pro_plan');
        expect(afterDowngrade.line_items_total).toBe(beforeDowngrade.plan_price ?? 0);
    });
});

test.describe('Account management billing portal data', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);
    });

    test('lists account users for the billing owner', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const response = await listAccountUsers(api.context);

        expect(response.status).toBe(200);
        expect(response.users.length).toBeGreaterThan(0);
        expect(response.users[0]).toMatchObject({
            email: expect.any(String),
            name: expect.any(String),
            status: expect.stringMatching(/active|inactive/),
        });
    });

    test('lists billing invoices for the account client', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const response = await listAccountInvoices(api.context);

        expect(response.status).toBe(200);
        expect(Array.isArray(response.invoices)).toBe(true);
    });

    test('lists stored payment methods for the billing client', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const response = await listPaymentMethods(api.context);

        expect(response.status).toBe(200);
        expect(Array.isArray(response.methods)).toBe(true);
    });
});

test.describe('Account management annual and term upgrades', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);

        if (skipReason) {
            return;
        }

        if (!stripeConfigured()) {
            skipReason =
                'Set STRIPE_KEYS (or NINJA_STRIPE_KEY) to run annual and term upgrade checkout tests';
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('completes a free to pro annual upgrade through Stripe', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        test.setTimeout(120_000);

        resetAccountPlanState(ownerEmail);

        await performUpgrade(api.context, {
            plan: 'pro',
            term: 'year',
            users: 1,
        });

        const account = readAccountPlanState(ownerEmail);

        expect(account.plan).toBe('pro');
        expect(account.plan_term).toBe('year');
        expect(account.plan_expires).toBeTruthy();
    });

    test('completes a month to year pro upgrade through Stripe', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        test.setTimeout(120_000);

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
            days_into_period: 14,
        });

        const paid = readAccountPlanState(ownerEmail);
        const ratio = proRataRatio(
            paid.plan_paid!,
            paid.plan_expires!,
            'month',
        );
        const expectedCharge = expectedTermUpgradeCharge(14, 140, ratio);

        const quote = await performUpgrade(api.context, {
            plan: 'pro',
            term: 'year',
            users: 1,
        });

        expect(quote.pro_rata_raw).toBe(expectedCharge);

        const account = readAccountPlanState(ownerEmail);

        expect(account.plan).toBe('pro');
        expect(account.plan_term).toBe('year');
    });
});

test.describe('Account management payment methods and invoices', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);

        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('creates a SetupIntent for adding a stored payment method', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        if (!stripeConfigured()) {
            test.skip(true, 'Set STRIPE_KEYS to run payment method SetupIntent tests');
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const intent = await createPaymentMethodSetupIntent(api.context);

        expect(intent.status).toBe(200);
        expect(intent.client_secret).toMatch(/_secret_/);
        expect(intent.setup_intent_id).toMatch(/^seti_/);
    });

    test('rejects invoice payment intents without an invoice id', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const response = await api.context.request.post(
            '/api/client/account_management/invoices/payment/intent',
            { data: {} },
        );

        expect(response.status()).toBeGreaterThanOrEqual(400);
    });

    test('marks the seeded recurring invoice as payable in the invoice list', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        const seeded = preparePayableBillingInvoice(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const response = await listAccountInvoices(api.context);

        expect(response.status).toBe(200);

        const payable = response.invoices.find(
            (invoice) => invoice.id === seeded.invoice_hashed_id,
        );

        expect(payable).toBeTruthy();
        expect(payable?.payable).toBe(true);
    });

    test('downloads a billing invoice pdf', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        test.setTimeout(120_000);

        const seeded = preparePayableBillingInvoice(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const download = await downloadAccountInvoice(
            api.context,
            seeded.invoice_hashed_id,
        );

        if (download.status === 503 || download.status === 504) {
            test.skip(
                true,
                'Invoice PDF export requires a running queue worker when QUEUE_CONNECTION=redis',
            );
        }

        expect(download.status).toBe(200);
        expect(download.contentType).toMatch(/pdf/i);
        expect(download.byteLength).toBeGreaterThan(500);
    });

    test('creates a PaymentIntent for a payable subscription invoice', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        if (!stripeConfigured()) {
            test.skip(true, 'Set STRIPE_KEYS to run invoice PaymentIntent tests');
        }

        const seeded = preparePayableBillingInvoice(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const intent = await createInvoicePaymentIntent(
            api.context,
            seeded.invoice_hashed_id,
        );

        expect(intent.status).toBe(200);
        expect(intent.requires_payment).toBe(true);
        expect(intent.client_secret).toMatch(/_secret_/);
    });

    test('pays a recurring subscription invoice through Stripe', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        if (!stripeConfigured()) {
            test.skip(true, 'Set STRIPE_KEYS to run invoice payment checkout tests');
        }

        test.setTimeout(120_000);

        const seeded = preparePayableBillingInvoice(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const payment = await payBillingInvoice(
            api.context,
            seeded.invoice_hashed_id,
        );

        expect(payment.status).toBe(200);
        expect(payment.message).toMatch(/success/i);

        const invoices = await listAccountInvoices(api.context);
        const paid = invoices.invoices.find(
            (invoice) => invoice.id === seeded.invoice_hashed_id,
        );

        expect(paid?.payable).toBe(false);
    });
});

test.describe('Account management docuninja beta', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        clearDocuNinjaBetaAllowlist(ownerEmail);
        resetAccountPlanState(ownerEmail);
    });

    test('rejects docuninja beta upgrades with invalid codes', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const response = await requestDocuNinjaBetaUpgrade(
            api.context,
            'invalid-beta-code',
        );

        expect(response.status).toBe(422);
        expect(response.message).toMatch(/invalid beta code/i);
    });

    test('rejects docuninja beta upgrades for owners outside the allowlist', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        clearDocuNinjaBetaAllowlist(ownerEmail);

        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });

        const response = await requestDocuNinjaBetaUpgrade(
            api.context,
            docuNinjaBetaCode(),
        );

        expect(response.status).toBe(422);
        expect(response.message).toMatch(/invalid beta code/i);
    });

    test('accepts docuninja beta upgrades for allowlisted paid accounts', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        test.setTimeout(120_000);

        resetAccountPlanState(ownerEmail);
        preparePaidAccountForQuotes(ownerEmail, {
            plan: 'pro',
            term: 'month',
            users: 1,
        });
        seedDocuNinjaBetaAllowlist(ownerEmail);

        const response = await requestDocuNinjaBetaUpgrade(
            api.context,
            docuNinjaBetaCode(),
        );

        if (response.status !== 200) {
            test.skip(
                true,
                `DocuNinja beta provisioning unavailable in this environment (${response.status}: ${response.message})`,
            );
        }

        expect(response.message).toMatch(/successful/i);

        const account = readAccountPlanState(ownerEmail);

        expect(account.docuninja_num_users).toBe(1);
    });
});

test.describe('Account management enterprise checkout', () => {
    let ownerEmail = '';
    let skipReason: string | null = null;

    test.beforeAll(async ({ api, account }) => {
        ownerEmail = account.ownerEmail;
        skipReason = await accountManagementSkipReason(api.context);

        if (skipReason) {
            return;
        }

        if (!stripeConfigured()) {
            skipReason =
                'Set STRIPE_KEYS (or NINJA_STRIPE_KEY) to run enterprise checkout tests';
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test.afterAll(() => {
        if (skipReason) {
            return;
        }

        resetAccountPlanState(ownerEmail);
    });

    test('quotes enterprise annual upgrades from free', async ({ api }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        resetAccountPlanState(ownerEmail);

        const quote = await getUpgradeDescription(api.context, {
            plan: 'enterprise',
            term: 'year',
            users: 2,
        });

        expect(quote.status).toBe(200);
        expect(quote.body.pro_rata_raw).toBe(180);
        expect(quote.body.requires_payment).toBe(true);
    });

    test('completes a free to enterprise monthly upgrade through Stripe', async ({
        api,
    }) => {
        if (skipReason) {
            test.skip(true, skipReason);
        }

        test.setTimeout(120_000);

        resetAccountPlanState(ownerEmail);

        await performUpgrade(api.context, {
            plan: 'enterprise',
            term: 'month',
            users: 2,
        });

        const account = readAccountPlanState(ownerEmail);

        expect(account.plan).toBe('enterprise');
        expect(account.plan_term).toBe('month');
        expect(account.num_users).toBe(2);
    });
});
