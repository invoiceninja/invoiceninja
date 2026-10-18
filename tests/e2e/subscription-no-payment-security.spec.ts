import { type Page, type Response } from '@playwright/test';
import { type ApiContext, type ApiEntity } from './api-helpers';
import {
    clearPortalOverlays,
    createAndLogInClient,
    type PortalClient,
} from './client-portal-helpers';
import { expect, test, type ApiFixture, uniqueName } from './fixtures';

type PurchaseVersion = 'v1' | 'v2';
type ProductSlot =
    'oneTime' | 'recurring' | 'optionalOneTime' | 'optionalRecurring';
type PurchaseDocumentType = 'invoices' | 'recurring_invoices';

interface PurchaseProduct extends ApiEntity {
    id: string;
    product_key: string;
    price?: number;
}

interface PurchaseSubscription extends ApiEntity {
    id: string;
    name?: string;
    price?: number;
    promo_price?: number;
}

interface PurchaseDocument extends ApiEntity {
    id: string;
    amount?: number;
    balance?: number;
    status_id?: string | number;
    subscription_id?: string | null;
    line_items?: Array<{
        product_key?: string;
        quantity?: number;
        cost?: number;
    }>;
}

interface SubscriptionScenario {
    oneTime?: number;
    recurring?: number;
    optionalOneTime?: number;
    optionalRecurring?: number;
    promoCode?: string;
    promoDiscount?: number;
    amountDiscount?: boolean;
    perSeat?: boolean;
    maxSeats?: number;
    initialCoupon?: string;
}

interface PurchaseDocuments {
    invoices: PurchaseDocument[];
    recurringInvoices: PurchaseDocument[];
}

interface PurchaseSession {
    api: ApiFixture;
    page: Page;
    version: PurchaseVersion;
    options: SubscriptionScenario;
    client: PortalClient;
    subscription: PurchaseSubscription;
    products: Partial<Record<ProductSlot, PurchaseProduct>>;
    documentsBefore: PurchaseDocuments;
}

interface LockedPropertyAttack {
    property: string;
    value: unknown;
}

const MONTHLY_FREQUENCY_ID = '5';
const PAID_STATUS_ID = '4';

const paidScenarios: Array<{
    name: string;
    options: SubscriptionScenario;
    total: number;
}> = [
    {
        name: 'one-time product',
        options: { oneTime: 50 },
        total: 50,
    },
    {
        name: 'recurring product',
        options: { recurring: 50 },
        total: 50,
    },
    {
        name: 'mixed one-time and recurring products',
        options: { oneTime: 25, recurring: 15 },
        total: 40,
    },
    {
        name: 'positive sub-unit product',
        options: { oneTime: 0.49 },
        total: 0.49,
    },
];

const zeroScenarios: Array<{
    name: string;
    options: SubscriptionScenario;
    createsRecurringInvoice: boolean;
}> = [
    {
        name: 'one-time product',
        options: { oneTime: 0 },
        createsRecurringInvoice: false,
    },
    {
        name: 'recurring product',
        options: { recurring: 0 },
        createsRecurringInvoice: true,
    },
    {
        name: 'mixed one-time and recurring products',
        options: { oneTime: 0, recurring: 0 },
        createsRecurringInvoice: true,
    },
];

test.describe('Subscription no-payment security — V1', () => {
    for (const scenario of paidScenarios) {
        test(`rejects the public no-payment action for a paid ${scenario.name}`, async ({
            api,
            page,
        }) => {
            const purchase = await startPurchase(
                api,
                page,
                'v1',
                scenario.options
            );

            await expectTrackedTotal(purchase, scenario.total);
            await expectPaymentRequired(purchase);
        });
    }

    test('rejects client mutations of every no-payment decision input', async ({
        api,
        page,
    }) => {
        test.setTimeout(120_000);

        const purchase = await startPurchase(api, page, 'v1', {
            oneTime: 50,
            perSeat: true,
            maxSeats: 5,
        });
        const attacks: LockedPropertyAttack[] = [
            { property: 'price', value: 0 },
            { property: 'quantity', value: 0 },
            { property: 'steps.payment_required', value: false },
        ];

        for (const attack of attacks) {
            await test.step(`cannot mutate ${attack.property}`, async () => {
                const response = await setLivewireProperty(
                    page,
                    'v1',
                    attack.property,
                    attack.value
                );

                expect(response.ok()).toBe(false);
                await reopenPurchase(purchase);
                await expectTrackedTotal(purchase, 50);
            });
        }

        await expectPaymentRequired(purchase);
    });

    for (const scenario of zeroScenarios) {
        test(`completes a genuine zero-total ${scenario.name} without a gateway`, async ({
            api,
            page,
            notificationGuard,
        }) => {
            await notificationGuard.suppressPaymentEmails();
            const purchase = await startPurchase(
                api,
                page,
                'v1',
                scenario.options
            );

            await expectTrackedTotal(purchase, 0);
            await completeNoPaymentPurchase(
                purchase,
                scenario.createsRecurringInvoice
            );
        });
    }

    test('applies a valid 100% coupon and exposes the legitimate no-payment flow', async ({
        api,
        page,
        notificationGuard,
    }) => {
        await notificationGuard.suppressPaymentEmails();
        const purchase = await startPurchase(api, page, 'v1', {
            oneTime: 50,
            promoCode: 'FREE100',
            promoDiscount: 100,
            initialCoupon: 'FREE100',
        });

        await expectTrackedTotal(purchase, 0);
        await completeNoPaymentPurchase(purchase, false);
    });

    test('keeps an invalid coupon on the paid path', async ({ api, page }) => {
        const purchase = await startPurchase(api, page, 'v1', {
            oneTime: 50,
            promoCode: 'FREE100',
            promoDiscount: 100,
        });

        const response = await applyCoupon(page, 'NOT-THE-CODE');
        expect(response.ok()).toBe(true);
        await expectTrackedTotal(purchase, 50);
        await expectPaymentRequired(purchase);
    });

    test('applies an automatic 100% discount with no coupon code', async ({
        api,
        page,
        notificationGuard,
    }) => {
        await notificationGuard.suppressPaymentEmails();
        const purchase = await startPurchase(api, page, 'v1', {
            oneTime: 50,
            promoCode: '',
            promoDiscount: 100,
        });

        await expectTrackedTotal(purchase, 0);
        await completeNoPaymentPurchase(purchase, false);
    });

    test('recalculates a normal per-seat increase and keeps payment required', async ({
        api,
        page,
    }) => {
        const purchase = await startPurchase(api, page, 'v1', {
            oneTime: 25,
            perSeat: true,
            maxSeats: 5,
        });

        const response = await clickV1SeatIncrement(page);
        expect(response.ok()).toBe(true);
        await expectTrackedTotal(purchase, 50);
        await expectPaymentRequired(purchase);
    });

    test('does not let the decrement control reduce a paid plan below one seat', async ({
        api,
        page,
    }) => {
        const purchase = await startPurchase(api, page, 'v1', {
            oneTime: 25,
            perSeat: true,
            maxSeats: 5,
        });

        const response = await clickV1SeatDecrement(page);
        expect(response.ok()).toBe(true);

        const trackedTotal = Number(
            await readLivewireProperty(page, 'v1', 'price')
        );
        const quantity = Number(
            await readLivewireProperty(page, 'v1', 'quantity')
        );
        const noPaymentResponse = await invokeNoPaymentAction(page, 'v1');

        expect.soft(trackedTotal).toBe(25);
        expect.soft(quantity).toBe(1);
        await expectGracefulPaymentRequiredError(page, noPaymentResponse);
        await expectNoNewDocuments(purchase);
    });

    test('preserves the fixed-discount no-payment flow after increasing seats', async ({
        api,
        page,
        notificationGuard,
    }) => {
        await notificationGuard.suppressPaymentEmails();
        const purchase = await startPurchase(api, page, 'v1', {
            oneTime: 25,
            promoCode: 'SAVE25',
            promoDiscount: 25,
            amountDiscount: true,
            perSeat: true,
            maxSeats: 5,
            initialCoupon: 'SAVE25',
        });

        await expectTrackedTotal(purchase, 0);

        expect((await clickV1SeatIncrement(page)).ok()).toBe(true);
        await expectTrackedTotal(purchase, 0);
        await completeNoPaymentPurchase(purchase, false);
    });
});

test.describe('Subscription no-payment security — V2', () => {
    for (const scenario of paidScenarios) {
        test(`rejects the public no-payment action for a paid ${scenario.name}`, async ({
            api,
            page,
        }) => {
            const purchase = await startPurchase(
                api,
                page,
                'v2',
                scenario.options
            );

            await expectTrackedTotal(purchase, scenario.total);
            await expectPaymentRequired(purchase);
        });
    }

    test('rejects client mutations of all server-owned pricing and identity state', async ({
        api,
        page,
    }) => {
        test.setTimeout(180_000);

        const purchase = await startPurchase(api, page, 'v2', {
            oneTime: 20,
            recurring: 30,
            optionalOneTime: 7,
            optionalRecurring: 5,
            promoCode: 'FREE100',
            promoDiscount: 100,
            perSeat: true,
            maxSeats: 5,
        });
        const attacks: LockedPropertyAttack[] = [
            { property: 'subscription_id', value: '1' },
            { property: 'price', value: 0 },
            { property: 'quantity', value: 0 },
            { property: 'float_amount_total', value: 0 },
            { property: 'valid_coupon', value: true },
            { property: 'bundle', value: [] },
            { property: 'products', value: [] },
            { property: 'recurring_products', value: [] },
            { property: 'optional_products', value: [] },
            { property: 'optional_recurring_products', value: [] },
            { property: 'payable_amount', value: 999 },
        ];

        for (const attack of attacks) {
            await test.step(`cannot mutate ${attack.property}`, async () => {
                const response = await setLivewireProperty(
                    page,
                    'v2',
                    attack.property,
                    attack.value
                );

                expect(response.ok()).toBe(false);
                await reopenPurchase(purchase);
                await expectTrackedTotal(purchase, 50);
            });
        }

        await expectPaymentRequired(purchase);
    });

    for (const validationScenario of [
        {
            name: 'required recurring quantity',
            field: 'data.0.recurring_qty',
        },
        {
            name: 'optional recurring quantity',
            field: 'data.0.optional_recurring_qty',
        },
        {
            name: 'optional one-time quantity',
            field: 'data.0.optional_qty',
        },
    ]) {
        test(`validates every invalid ${validationScenario.name} before rebuilding totals`, async ({
            api,
            page,
        }) => {
            test.setTimeout(120_000);

            const purchase = await startPurchase(api, page, 'v2', {
                recurring: 25,
                optionalRecurring: 5,
                optionalOneTime: 7,
                perSeat: true,
                maxSeats: 5,
            });

            for (const invalidValue of invalidQuantityValues(
                validationScenario.field
            )) {
                await test.step(`rejects ${JSON.stringify(invalidValue)}`, async () => {
                    const response = await setLivewireProperty(
                        page,
                        'v2',
                        validationScenario.field,
                        invalidValue
                    );

                    expect(response.ok()).toBe(true);
                    const errors = await livewireValidationErrors(response);
                    expect(Object.keys(errors)).toContain(
                        validationScenario.field
                    );

                    const noPaymentResponse = await invokeNoPaymentAction(
                        page,
                        'v2'
                    );
                    await expectGracefulPaymentRequiredError(
                        page,
                        noPaymentResponse
                    );
                    await expectNoNewDocuments(purchase);
                    await reopenPurchase(purchase);
                });
            }
        });
    }

    test('recalculates valid required and optional quantities through the rendered controls', async ({
        api,
        page,
    }) => {
        const purchase = await startPurchase(api, page, 'v2', {
            recurring: 25,
            optionalRecurring: 5,
            optionalOneTime: 7,
            perSeat: true,
            maxSeats: 5,
        });

        expect(
            (
                await selectLivewireQuantity(page, 'data.0.recurring_qty', '2')
            ).ok()
        ).toBe(true);
        expect(
            (
                await selectLivewireQuantity(
                    page,
                    'data.0.optional_recurring_qty',
                    '3'
                )
            ).ok()
        ).toBe(true);
        expect(
            (
                await selectLivewireQuantity(page, 'data.0.optional_qty', '4')
            ).ok()
        ).toBe(true);

        await expectTrackedTotal(purchase, 93);
        await expect(page.locator('#summary')).toContainText('2 x');
        await expect(page.locator('#summary')).toContainText('3 x');
        await expect(page.locator('#summary')).toContainText('4 x');
        await expectPaymentRequired(purchase);
    });

    for (const scenario of zeroScenarios) {
        test(`completes a genuine zero-total ${scenario.name} without a gateway`, async ({
            api,
            page,
            notificationGuard,
        }) => {
            await notificationGuard.suppressPaymentEmails();
            const purchase = await startPurchase(
                api,
                page,
                'v2',
                scenario.options
            );

            await expectTrackedTotal(purchase, 0);
            await completeNoPaymentPurchase(
                purchase,
                scenario.createsRecurringInvoice
            );
        });
    }

    test('applies a 100% coupon after a recurring quantity change and preserves the purchased quantity', async ({
        api,
        page,
        notificationGuard,
    }) => {
        await notificationGuard.suppressPaymentEmails();
        const purchase = await startPurchase(api, page, 'v2', {
            recurring: 25,
            promoCode: 'FREE100',
            promoDiscount: 100,
            perSeat: true,
            maxSeats: 5,
        });

        expect(
            (
                await selectLivewireQuantity(page, 'data.0.recurring_qty', '3')
            ).ok()
        ).toBe(true);
        await expectTrackedTotal(purchase, 75);

        expect((await applyCoupon(page, 'FREE100')).ok()).toBe(true);
        await expectTrackedTotal(purchase, 0);
        const invoice = await completeNoPaymentPurchase(purchase, true);

        expectLineItemQuantity(
            invoice,
            purchase.products.recurring?.product_key,
            3
        );
    });

    test('recalculates an exact fixed discount when quantity later increases', async ({
        api,
        page,
    }) => {
        const purchase = await startPurchase(api, page, 'v2', {
            recurring: 25,
            promoCode: 'SAVE25',
            promoDiscount: 25,
            amountDiscount: true,
            perSeat: true,
            maxSeats: 5,
        });

        expect((await applyCoupon(page, 'SAVE25')).ok()).toBe(true);
        await expectTrackedTotal(purchase, 0);
        await expect(noPaymentForm(page)).toBeVisible();

        expect(
            (
                await selectLivewireQuantity(page, 'data.0.recurring_qty', '2')
            ).ok()
        ).toBe(true);
        await expectTrackedTotal(purchase, 25);
        await expectPaymentRequired(purchase);
    });

    test('keeps an invalid coupon on the paid path and returns a validation error', async ({
        api,
        page,
    }) => {
        const purchase = await startPurchase(api, page, 'v2', {
            oneTime: 50,
            promoCode: 'FREE100',
            promoDiscount: 100,
        });

        const response = await applyCoupon(page, 'NOT-THE-CODE');
        expect(response.ok()).toBe(true);
        const errors = await livewireValidationErrors(response);
        expect(Object.keys(errors)).toContain('coupon');
        await expectTrackedTotal(purchase, 50);
        await expectPaymentRequired(purchase);
    });

    test('does not treat an over-discounted negative computed total as zero', async ({
        api,
        page,
    }) => {
        const purchase = await startPurchase(api, page, 'v2', {
            oneTime: 10,
            promoCode: 'SAVE20',
            promoDiscount: 20,
            amountDiscount: true,
        });

        expect((await applyCoupon(page, 'SAVE20')).ok()).toBe(true);
        await expectTrackedTotal(purchase, -10);
        await expectPaymentRequired(purchase);
    });

    test('does not infer a V2 coupon when no promo code is configured', async ({
        api,
        page,
    }) => {
        const purchase = await startPurchase(api, page, 'v2', {
            oneTime: 50,
            promoCode: '',
            promoDiscount: 100,
        });

        await expectTrackedTotal(purchase, 50);
        await expectPaymentRequired(purchase);
    });

    test('moves from zero to paid and back to zero as an optional product is selected and removed', async ({
        api,
        page,
        notificationGuard,
    }) => {
        await notificationGuard.suppressPaymentEmails();
        const purchase = await startPurchase(api, page, 'v2', {
            oneTime: 0,
            optionalOneTime: 12,
        });

        await expectTrackedTotal(purchase, 0);
        await expect(noPaymentForm(page)).toBeVisible();

        expect(
            (
                await selectLivewireQuantity(page, 'data.0.optional_qty', '1')
            ).ok()
        ).toBe(true);
        await expectTrackedTotal(purchase, 12);
        await expectPaymentRequired(purchase);

        expect(
            (
                await selectLivewireQuantity(page, 'data.0.optional_qty', '0')
            ).ok()
        ).toBe(true);
        await expectTrackedTotal(purchase, 0);
        const invoice = await completeNoPaymentPurchase(purchase, false);

        expect(
            invoice.line_items?.some(
                (item) =>
                    item.product_key ===
                    purchase.products.optionalOneTime?.product_key
            ) ?? false
        ).toBe(false);
    });
});

function invalidQuantityValues(field: string): unknown[] {
    if (field === 'data.0.recurring_qty') {
        return [0, -1, 1.5, 'not-a-number'];
    }

    return [-1, 1.5, 'not-a-number'];
}

async function startPurchase(
    api: ApiFixture,
    page: Page,
    version: PurchaseVersion,
    options: SubscriptionScenario
): Promise<PurchaseSession> {
    const { subscription, products } = await createPurchaseSubscription(
        api,
        options
    );
    const client = await createAndLogInClient(api, page, {
        name: uniqueName(`subscription-${version}-buyer`),
        contact: {
            first_name: 'Subscription',
            last_name: 'Buyer',
            password: 'Portal123',
        },
    });
    const documentsBefore = await clientPurchaseDocuments(
        api.context,
        client.id
    );
    const purchase: PurchaseSession = {
        api,
        page,
        version,
        options,
        client,
        subscription,
        products,
        documentsBefore,
    };

    await openPurchase(purchase);

    return purchase;
}

async function createPurchaseSubscription(
    api: ApiFixture,
    options: SubscriptionScenario
): Promise<{
    subscription: PurchaseSubscription;
    products: Partial<Record<ProductSlot, PurchaseProduct>>;
}> {
    const marker = uniqueName('no-payment');
    const products: Partial<Record<ProductSlot, PurchaseProduct>> = {};
    const definitions: Array<{
        slot: ProductSlot;
        price: number | undefined;
        label: string;
    }> = [
        { slot: 'oneTime', price: options.oneTime, label: 'one-time' },
        { slot: 'recurring', price: options.recurring, label: 'recurring' },
        {
            slot: 'optionalOneTime',
            price: options.optionalOneTime,
            label: 'optional-one-time',
        },
        {
            slot: 'optionalRecurring',
            price: options.optionalRecurring,
            label: 'optional-recurring',
        },
    ];

    for (const definition of definitions) {
        if (definition.price === undefined) {
            continue;
        }

        products[definition.slot] = await api.createEntity<PurchaseProduct>(
            'products',
            {
                product_key: `${marker}-${definition.label}`,
                notes: `${marker} ${definition.label}`,
                cost: definition.price,
                price: definition.price,
                quantity: 1,
                max_quantity: options.maxSeats ?? 5,
            }
        );
    }

    const subscription = await api.createEntity<PurchaseSubscription>(
        'subscriptions',
        {
            name: marker,
            steps: 'cart,auth.login-or-register',
            product_ids: products.oneTime?.id ?? '',
            recurring_product_ids: products.recurring?.id ?? '',
            optional_product_ids: products.optionalOneTime?.id ?? '',
            optional_recurring_product_ids:
                products.optionalRecurring?.id ?? '',
            frequency_id: MONTHLY_FREQUENCY_ID,
            auto_bill: 'off',
            remaining_cycles: -1,
            allow_cancellation: true,
            allow_plan_changes: false,
            registration_required: false,
            per_seat_enabled: options.perSeat ?? false,
            max_seats_limit: options.maxSeats ?? 5,
            promo_code: options.promoCode ?? '',
            promo_discount: options.promoDiscount ?? 0,
            is_amount_discount: options.amountDiscount ?? false,
        }
    );

    return { subscription, products };
}

async function openPurchase(purchase: PurchaseSession): Promise<void> {
    const suffix = purchase.version === 'v2' ? '/v2' : '';
    const query = purchase.options.initialCoupon
        ? `?coupon=${encodeURIComponent(purchase.options.initialCoupon)}`
        : '';
    const response = await purchase.page.goto(
        `/client/subscriptions/${purchase.subscription.id}/purchase${suffix}${query}`
    );

    expect(response?.ok()).toBe(true);
    await clearPortalOverlays(purchase.page);
    await purchaseComponentId(purchase.page, purchase.version);
    await expect(
        purchase.page.locator('#billing-page-company-logo')
    ).toContainText(purchase.subscription.name ?? '');

    if (purchase.version === 'v2') {
        await expect
            .poll(
                async () =>
                    Boolean(
                        await readLivewireProperty(purchase.page, 'v2', 'total')
                    ),
                { timeout: 20_000 }
            )
            .toBe(true);
    }
}

async function reopenPurchase(purchase: PurchaseSession): Promise<void> {
    await openPurchase(purchase);
}

async function purchaseComponentId(
    page: Page,
    version: PurchaseVersion
): Promise<string> {
    await page.waitForFunction(() =>
        Boolean(
            (
                window as unknown as {
                    Livewire?: { find?: (id: string) => unknown };
                }
            ).Livewire?.find
        )
    );

    const expectedName =
        version === 'v2'
            ? 'billing-portal-purchasev2'
            : 'billing-portal-purchase';
    const componentId = await page
        .locator('[wire\\:id][wire\\:snapshot]')
        .evaluateAll((roots, name) => {
            for (const root of roots) {
                const snapshot = root.getAttribute('wire:snapshot');

                if (!snapshot) {
                    continue;
                }

                try {
                    const parsed = JSON.parse(snapshot) as {
                        memo?: { name?: string };
                    };

                    if (parsed.memo?.name === name) {
                        return root.getAttribute('wire:id');
                    }
                } catch {
                    continue;
                }
            }

            return null;
        }, expectedName);

    if (!componentId) {
        throw new Error(`Unable to find Livewire component ${expectedName}.`);
    }

    return componentId;
}

async function readLivewireProperty(
    page: Page,
    version: PurchaseVersion,
    property: string
): Promise<unknown> {
    const id = await purchaseComponentId(page, version);

    return page.evaluate(
        ({ componentId, propertyName }) => {
            const livewire = (
                window as unknown as {
                    Livewire: {
                        find: (id: string) => {
                            get: (property: string) => unknown;
                        };
                    };
                }
            ).Livewire;

            return livewire.find(componentId).get(propertyName);
        },
        { componentId: id, propertyName: property }
    );
}

async function setLivewireProperty(
    page: Page,
    version: PurchaseVersion,
    property: string,
    value: unknown
): Promise<Response> {
    const id = await purchaseComponentId(page, version);

    return captureLivewireResponse(page, async () => {
        await page.evaluate(
            ({ componentId, propertyName, nextValue }) => {
                const livewire = (
                    window as unknown as {
                        Livewire: {
                            find: (id: string) => {
                                set: (
                                    property: string,
                                    value: unknown
                                ) => Promise<unknown>;
                            };
                        };
                    }
                ).Livewire;

                void livewire
                    .find(componentId)
                    .set(propertyName, nextValue)
                    .catch(() => undefined);
            },
            {
                componentId: id,
                propertyName: property,
                nextValue: value,
            }
        );
    });
}

async function invokeNoPaymentAction(
    page: Page,
    version: PurchaseVersion
): Promise<Response> {
    return invokeLivewireAction(page, version, 'handlePaymentNotRequired');
}

async function invokeLivewireAction(
    page: Page,
    version: PurchaseVersion,
    method: string,
    parameters: unknown[] = []
): Promise<Response> {
    const id = await purchaseComponentId(page, version);

    return captureLivewireResponse(page, async () => {
        await page.evaluate(
            ({ componentId, methodName, methodParameters }) => {
                const livewire = (
                    window as unknown as {
                        Livewire: {
                            find: (id: string) => {
                                call: (
                                    method: string,
                                    ...parameters: unknown[]
                                ) => Promise<unknown>;
                            };
                        };
                    }
                ).Livewire;

                void livewire
                    .find(componentId)
                    .call(methodName, ...methodParameters)
                    .catch(() => undefined);
            },
            {
                componentId: id,
                methodName: method,
                methodParameters: parameters,
            }
        );
    });
}

async function captureLivewireResponse(
    page: Page,
    action: () => Promise<void>
): Promise<Response> {
    const responsePromise = page.waitForResponse(
        (response) =>
            /\/livewire(?:\/|$)/.test(response.url()) &&
            response.request().method() === 'POST',
        { timeout: 30_000 }
    );

    await action();

    return responsePromise;
}

async function applyCoupon(page: Page, coupon: string): Promise<Response> {
    const form = page.locator('form[wire\\:submit="handleCoupon"]');
    await expect(form).toBeVisible();
    await form.locator('input[wire\\:model="coupon"]').fill(coupon);

    return captureLivewireResponse(page, async () => {
        await form.locator('button').click();
    });
}

async function clickV1SeatIncrement(page: Page): Promise<Response> {
    const button = page.locator(
        'button[wire\\:click*="updateQuantity"][wire\\:click*="increment"]'
    );
    await expect(button).toBeVisible();

    return captureLivewireResponse(page, async () => {
        await button.click();
    });
}

async function clickV1SeatDecrement(page: Page): Promise<Response> {
    const button = page.locator(
        'button[wire\\:click*="updateQuantity"][wire\\:click*="decrement"]'
    );
    await expect(button).toBeVisible();

    return captureLivewireResponse(page, async () => {
        await button.click();
    });
}

async function selectLivewireQuantity(
    page: Page,
    property: string,
    value: string
): Promise<Response> {
    const select = page.locator(
        `select[wire\\:model\\.live\\.debounce\\.300ms="${property}"]`
    );
    await expect(select).toBeVisible();

    return captureLivewireResponse(page, async () => {
        await select.selectOption(value);
    });
}

async function livewireValidationErrors(
    response: Response
): Promise<Record<string, unknown>> {
    const payload = (await response.json()) as {
        components?: Array<{ snapshot?: string }>;
    };

    for (const component of payload.components ?? []) {
        if (!component.snapshot) {
            continue;
        }

        const snapshot = JSON.parse(component.snapshot) as {
            memo?: { errors?: Record<string, unknown> };
        };

        if (snapshot.memo?.errors) {
            return snapshot.memo.errors;
        }
    }

    return {};
}

function noPaymentForm(page: Page) {
    return page.locator('form[wire\\:submit="handlePaymentNotRequired"]');
}

async function expectTrackedTotal(
    purchase: PurchaseSession,
    expected: number
): Promise<void> {
    const property = purchase.version === 'v2' ? 'float_amount_total' : 'price';

    await expect
        .poll(
            async () =>
                Number(
                    await readLivewireProperty(
                        purchase.page,
                        purchase.version,
                        property
                    )
                ),
            { timeout: 20_000 }
        )
        .toBeCloseTo(expected, 6);
}

async function expectPaymentRequired(purchase: PurchaseSession): Promise<void> {
    await expect(noPaymentForm(purchase.page)).toHaveCount(0);

    const response = await invokeNoPaymentAction(
        purchase.page,
        purchase.version
    );
    await expectGracefulPaymentRequiredError(purchase.page, response);
    await expectNoNewDocuments(purchase);
}

async function expectGracefulPaymentRequiredError(
    page: Page,
    response: Response
): Promise<void> {
    expect(response.ok()).toBe(true);
    await expect(page.getByTestId('payment-required-error')).toContainText(
        'The total has changed and payment is now required.'
    );
}

async function completeNoPaymentPurchase(
    purchase: PurchaseSession,
    createsRecurringInvoice: boolean
): Promise<PurchaseDocument> {
    const form = noPaymentForm(purchase.page);
    await expect(form).toBeVisible();

    const responsePromise = purchase.page.waitForResponse(
        (response) =>
            /\/livewire(?:\/|$)/.test(response.url()) &&
            response.request().method() === 'POST',
        { timeout: 30_000 }
    );
    const redirectPromise = purchase.page.waitForURL(
        createsRecurringInvoice
            ? /\/client\/recurring_invoices\/[^/?]+/
            : /\/client\/invoices\/[^/?]+/,
        { timeout: 30_000 }
    );

    await form.locator('button').click();
    const response = await responsePromise;
    expect(response.ok()).toBe(true);
    await redirectPromise;

    const expectedCounts = {
        invoices: purchase.documentsBefore.invoices.length + 1,
        recurringInvoices:
            purchase.documentsBefore.recurringInvoices.length +
            (createsRecurringInvoice ? 1 : 0),
    };

    await expect
        .poll(async () => {
            const documents = await clientPurchaseDocuments(
                purchase.api.context,
                purchase.client.id
            );

            return {
                invoices: documents.invoices.length,
                recurringInvoices: documents.recurringInvoices.length,
            };
        })
        .toEqual(expectedCounts);

    const documentsAfter = await clientPurchaseDocuments(
        purchase.api.context,
        purchase.client.id
    );
    const invoice = trackCreatedDocuments(purchase, documentsAfter);

    expect(String(invoice.status_id)).toBe(PAID_STATUS_ID);
    expect(Number(invoice.amount)).toBe(0);
    expect(Number(invoice.balance)).toBe(0);
    expect(invoice.subscription_id).toBe(purchase.subscription.id);

    if (createsRecurringInvoice) {
        const recurring = documentsAfter.recurringInvoices.find(
            (document) =>
                !purchase.documentsBefore.recurringInvoices.some(
                    (before) => before.id === document.id
                )
        );

        expect(recurring?.subscription_id).toBe(purchase.subscription.id);
    }

    return invoice;
}

function trackCreatedDocuments(
    purchase: PurchaseSession,
    documentsAfter: PurchaseDocuments
): PurchaseDocument {
    const beforeInvoiceIds = new Set(
        purchase.documentsBefore.invoices.map((invoice) => invoice.id)
    );
    const beforeRecurringIds = new Set(
        purchase.documentsBefore.recurringInvoices.map(
            (recurring) => recurring.id
        )
    );
    const newInvoices = documentsAfter.invoices.filter(
        (invoice) => !beforeInvoiceIds.has(invoice.id)
    );
    const newRecurringInvoices = documentsAfter.recurringInvoices.filter(
        (recurring) => !beforeRecurringIds.has(recurring.id)
    );

    for (const invoice of newInvoices) {
        purchase.api.trackEntity('invoices', invoice.id);
    }

    for (const recurring of newRecurringInvoices) {
        purchase.api.trackEntity('recurring_invoices', recurring.id);
    }

    expect(newInvoices).toHaveLength(1);

    return newInvoices[0];
}

function expectLineItemQuantity(
    invoice: PurchaseDocument,
    productKey: string | undefined,
    expectedQuantity: number
): void {
    if (!productKey) {
        throw new Error('Expected the purchase product to have a product key.');
    }

    const lineItem = invoice.line_items?.find(
        (item) => item.product_key === productKey
    );

    expect(lineItem).toBeDefined();
    expect(Number(lineItem?.quantity)).toBe(expectedQuantity);
}

async function expectNoNewDocuments(purchase: PurchaseSession): Promise<void> {
    const documentsAfter = await clientPurchaseDocuments(
        purchase.api.context,
        purchase.client.id
    );

    trackUnexpectedDocuments(purchase, documentsAfter);

    expect(documentIds(documentsAfter.invoices)).toEqual(
        documentIds(purchase.documentsBefore.invoices)
    );
    expect(documentIds(documentsAfter.recurringInvoices)).toEqual(
        documentIds(purchase.documentsBefore.recurringInvoices)
    );
}

function trackUnexpectedDocuments(
    purchase: PurchaseSession,
    documentsAfter: PurchaseDocuments
): void {
    const beforeInvoiceIds = new Set(
        purchase.documentsBefore.invoices.map((invoice) => invoice.id)
    );
    const beforeRecurringIds = new Set(
        purchase.documentsBefore.recurringInvoices.map(
            (recurring) => recurring.id
        )
    );

    for (const invoice of documentsAfter.invoices) {
        if (!beforeInvoiceIds.has(invoice.id)) {
            purchase.api.trackEntity('invoices', invoice.id);
        }
    }

    for (const recurring of documentsAfter.recurringInvoices) {
        if (!beforeRecurringIds.has(recurring.id)) {
            purchase.api.trackEntity('recurring_invoices', recurring.id);
        }
    }
}

function documentIds(documents: PurchaseDocument[]): string[] {
    return documents.map((document) => document.id).sort();
}

async function clientPurchaseDocuments(
    api: ApiContext,
    clientId: string
): Promise<PurchaseDocuments> {
    const [invoices, recurringInvoices] = await Promise.all([
        listClientDocuments(api, 'invoices', clientId),
        listClientDocuments(api, 'recurring_invoices', clientId),
    ]);

    return { invoices, recurringInvoices };
}

async function listClientDocuments(
    api: ApiContext,
    type: PurchaseDocumentType,
    clientId: string
): Promise<PurchaseDocument[]> {
    const response = await api.request.get(
        `/api/v1/${type}?client_id=${clientId}&per_page=100`
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to list ${type} (${response.status()}): ${(await response.text()).slice(0, 300)}`
        );
    }

    const body = (await response.json()) as { data?: PurchaseDocument[] };

    return body.data ?? [];
}
