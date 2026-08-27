import { expect, test } from '@playwright/test';
import {
    bootSquareCreditCard,
    buildVerificationDetails,
    resolveSquarePaymentContainer,
    tokenizeCard,
} from '../../resources/js/clients/payments/square-credit-card.js';

test('builds customer-initiated charge verification details', () => {
    const billingContact = {
        countryCode: 'DE',
        postalCode: '10115',
    };

    expect(
        buildVerificationDetails('CHARGE', billingContact, '10.00', 'EUR')
    ).toEqual({
        amount: '10.00',
        billingContact,
        currencyCode: 'EUR',
        customerInitiated: true,
        intent: 'CHARGE',
        sellerKeyedIn: false,
    });
});

test('omits charge-only fields when storing a card', () => {
    const billingContact = {
        countryCode: 'DE',
        postalCode: '10115',
    };

    expect(
        buildVerificationDetails('STORE', billingContact, null, 'EUR')
    ).toEqual({
        billingContact,
        customerInitiated: true,
        intent: 'STORE',
        sellerKeyedIn: false,
    });
});

test('re-tokenizes a stored card before a customer-present payment', async () => {
    const calls: unknown[][] = [];
    const card = {
        tokenize: async (...parameters: unknown[]) => {
            calls.push(parameters);

            return {
                status: 'OK',
                token: 'verified-payment-token',
            };
        },
    };
    const verificationDetails = buildVerificationDetails(
        'CHARGE',
        { countryCode: 'DE' },
        '10.00',
        'EUR'
    );

    const token = await tokenizeCard(
        card,
        verificationDetails,
        'stored-card-id'
    );

    expect(token).toBe('verified-payment-token');
    expect(calls).toEqual([[verificationDetails, 'stored-card-id']]);
});

test('surfaces Square tokenization failures', async () => {
    const card = {
        tokenize: async () => ({
            errors: [{ message: 'Verification was not completed.' }],
            status: 'ERROR',
        }),
    };

    await expect(
        tokenizeCard(
            card,
            buildVerificationDetails(
                'CHARGE_AND_STORE',
                { countryCode: 'DE' },
                '10.00',
                'EUR'
            )
        )
    ).rejects.toThrow('Verification was not completed.');
});

test('uses the standard payment form as the classic initialization target', async () => {
    const form = { dataset: {} };
    const documentRoot = {
        getElementById: (id: string) =>
            id === 'server_response' ? form : null,
        getElementsByClassName: () => [],
    };
    let handleCalls = 0;

    expect(resolveSquarePaymentContainer(documentRoot)).toBe(form);
    expect(
        await bootSquareCreditCard(documentRoot, () => ({
            handle: async () => {
                handleCalls++;
            },
        }))
    ).toBe(true);
    expect(handleCalls).toBe(1);
    expect(form.dataset.squareInitialized).toBe('true');
});

test('prefers the Livewire component root and initializes it only once', async () => {
    const livewireContainer = { dataset: {} };
    const form = { dataset: {} };
    const elements = {
        'square-credit-card-payment': livewireContainer,
        server_response: form,
    };
    const documentRoot = {
        getElementById: (id: keyof typeof elements) => elements[id] ?? null,
        getElementsByClassName: () => [],
    };
    let handleCalls = 0;
    const createSquareCreditCard = () => ({
        handle: async () => {
            handleCalls++;
        },
    });

    expect(resolveSquarePaymentContainer(documentRoot)).toBe(livewireContainer);
    expect(
        await bootSquareCreditCard(documentRoot, createSquareCreditCard)
    ).toBe(true);
    expect(
        await bootSquareCreditCard(documentRoot, createSquareCreditCard)
    ).toBe(false);
    expect(handleCalls).toBe(1);
    expect(livewireContainer.dataset.squareInitialized).toBe('true');
    expect(form.dataset.squareInitialized).toBeUndefined();
});

test('reports initialization failures and permits a retry', async () => {
    const form = { dataset: {} };
    const errors = { hidden: true, textContent: '' };
    let loaderWasHidden = false;
    const documentRoot = {
        getElementById: (id: string) => {
            if (id === 'server_response') {
                return form;
            }

            if (id === 'errors') {
                return errors;
            }

            return null;
        },
        getElementsByClassName: () => [
            {
                classList: {
                    add: (className: string) => {
                        loaderWasHidden = className === 'hidden';
                    },
                },
            },
        ],
    };

    expect(
        await bootSquareCreditCard(documentRoot, () => ({
            handle: async () => {
                throw new Error('Square SDK failed to load.');
            },
        }))
    ).toBe(false);
    expect(form.dataset.squareInitialized).toBeUndefined();
    expect(errors.hidden).toBe(false);
    expect(errors.textContent).toBe('Square SDK failed to load.');
    expect(loaderWasHidden).toBe(true);
});
