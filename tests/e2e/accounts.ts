import { resolvePlaywrightUrls } from './environment';

/**
 * Account lanes mirror ../ui so parallel tests use isolated seeded companies.
 */
export interface TestAccount {
    id: number;
    ownerEmail: string;
    password: string;
    apiUrl: string;
}

const DEFAULT_ACCOUNT_COUNT = 8;
const DEFAULT_OWNER_EMAIL = 'user@example.com';
const DEFAULT_PASSWORD = 'password';
export const SMALL_ACCOUNT_EMAIL = 'small@example.com';

const accountCount = positiveInt(
    process.env.PLAYWRIGHT_ACCOUNT_COUNT ?? process.env.E2E_ACCOUNT_COUNT,
    DEFAULT_ACCOUNT_COUNT,
);
const accountOffset = nonNegativeInt(
    process.env.PLAYWRIGHT_ACCOUNT_OFFSET,
    0,
);

export function accountForParallelIndex(parallelIndex: number): TestAccount {
    const accountId = accountOffset + parallelIndex + 1;

    if (accountId > accountCount) {
        throw new Error(
            `Playwright worker requires account lane ${accountId}, but only ${accountCount} lanes are configured.`,
        );
    }

    return createTestAccount(accountId);
}

/** Hosted-plan / white-label purchase lane (matches NINJA_COMPANY_ID). */
export function smallTestAccount(): TestAccount {
    return {
        id: 0,
        ownerEmail: SMALL_ACCOUNT_EMAIL,
        password: DEFAULT_PASSWORD,
        apiUrl: resolvePlaywrightUrls().apiUrl,
    };
}

export function createTestAccount(id: number): TestAccount {
    const ownerEmail =
        process.env[`PLAYWRIGHT_ACCOUNT_${id}_EMAIL`] ??
        accountEmail(process.env.PLAYWRIGHT_OWNER_EMAIL ?? DEFAULT_OWNER_EMAIL, id);

    return {
        id,
        ownerEmail,
        password:
            process.env[`PLAYWRIGHT_ACCOUNT_${id}_PASSWORD`] ??
            process.env.PLAYWRIGHT_ACCOUNT_PASSWORD ??
            DEFAULT_PASSWORD,
        apiUrl: resolvePlaywrightUrls().apiUrl,
    };
}

export function accountEmail(email: string, accountId: number): string {
    const [localPart, domain] = email.split('@');

    // Match ../ui: custom owner emails stay unchanged unless an explicit
    // PLAYWRIGHT_ACCOUNT_<n>_EMAIL override is supplied.
    if (!localPart || !domain || localPart !== 'user') {
        return email;
    }

    return `${localPart}${accountId}@${domain}`;
}

function positiveInt(value: string | undefined, fallback: number): number {
    const parsed = Number.parseInt(value ?? '', 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function nonNegativeInt(value: string | undefined, fallback: number): number {
    const parsed = Number.parseInt(value ?? '', 10);

    return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallback;
}
