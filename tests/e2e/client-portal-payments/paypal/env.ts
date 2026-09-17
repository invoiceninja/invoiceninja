export interface PayPalRestKeys {
    clientId: string;
    secret: string;
    testMode: boolean;
    buyerEmail?: string;
    buyerPassword?: string;
}

function normalizePayPalRestKeys(
    parsed: Record<string, unknown>,
): PayPalRestKeys | null {
    const clientId = String(parsed.clientId ?? '').trim();
    const secret = String(parsed.secret ?? '').trim();
    const testMode = Boolean(parsed.testMode);
    const buyerEmail = String(
        parsed.buyerEmail ?? parsed.sandboxBuyerEmail ?? '',
    ).trim();
    const buyerPassword = String(
        parsed.buyerPassword ?? parsed.sandboxBuyerPassword ?? '',
    ).trim();

    if (!clientId || !secret) {
        return null;
    }

    return {
        clientId,
        secret,
        testMode,
        ...(buyerEmail ? { buyerEmail } : {}),
        ...(buyerPassword ? { buyerPassword } : {}),
    };
}

/**
 * Recover credentials from `.env` values where JSON.parse fails because a
 * sandbox password contains unescaped double quotes.
 */
function parsePayPalRestKeysLenient(raw: string): PayPalRestKeys | null {
    const clientId = raw.match(/"clientId"\s*:\s*"([^"]+)"/)?.[1]?.trim();
    const secret = raw.match(/"secret"\s*:\s*"([^"]+)"/)?.[1]?.trim();
    const buyerEmail = raw
        .match(/"buyerEmail"\s*:\s*"([^"]+)"/)?.[1]
        ?.trim();
    const testMode = !/"testMode"\s*:\s*false/.test(raw);

    let buyerPassword = raw
        .match(/"buyerPassword"\s*:\s*"([^"]*)"/)?.[1]
        ?.trim();

    const passwordStart = raw.indexOf('"buyerPassword":"');

    if (passwordStart >= 0) {
        const valueStart = passwordStart + '"buyerPassword":"'.length;
        const clientIdMarker = '","clientId":"';
        const clientIdIndex = raw.indexOf(clientIdMarker, valueStart);

        if (clientIdIndex > valueStart) {
            buyerPassword = raw.slice(valueStart, clientIdIndex);
        }
    }

    if (!clientId || !secret) {
        return null;
    }

    return {
        clientId,
        secret,
        testMode,
        ...(buyerEmail ? { buyerEmail } : {}),
        ...(buyerPassword ? { buyerPassword } : {}),
    };
}

function parsePayPalRestKeysFromFlatEnv(): PayPalRestKeys | null {
    const clientId = process.env.PAYPAL_REST_CLIENT_ID?.trim() ?? '';
    const secret = process.env.PAYPAL_REST_SECRET?.trim() ?? '';

    if (!clientId || !secret) {
        return null;
    }

    const buyerEmail = process.env.PAYPAL_SANDBOX_BUYER_EMAIL?.trim();
    const buyerPassword = process.env.PAYPAL_SANDBOX_BUYER_PASSWORD?.trim();
    const testMode = process.env.PAYPAL_REST_TEST_MODE !== 'false';

    return {
        clientId,
        secret,
        testMode,
        ...(buyerEmail ? { buyerEmail } : {}),
        ...(buyerPassword ? { buyerPassword } : {}),
    };
}

function parsePayPalRestKeysFromJsonEnv(): PayPalRestKeys | null {
    const raw = process.env.PAYPAL_REST_KEYS?.trim() ?? '';

    if (!raw) {
        return null;
    }

    try {
        const parsed = JSON.parse(raw) as Record<string, unknown>;

        return normalizePayPalRestKeys(parsed);
    } catch {
        return parsePayPalRestKeysLenient(raw);
    }
}

export function parsePayPalRestKeys(): PayPalRestKeys | null {
    return (
        parsePayPalRestKeysFromJsonEnv() ?? parsePayPalRestKeysFromFlatEnv()
    );
}
