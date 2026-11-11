import { execFileSync } from 'node:child_process';
import { createHmac } from 'node:crypto';
import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

function runArtisanExecute(phpCode: string): string {
    const output = execFileSync(
        'php',
        ['artisan', 'tinker', '--execute', phpCode],
        {
            cwd: projectRoot,
            encoding: 'utf8',
            env: process.env,
        },
    );

    return output.trim();
}

/**
 * Returns true when artisan tinker can see a contact created via the remote API.
 * Remote APP_URL + local DB mismatches cause auth helpers to be unavailable.
 */
export function contactExistsInLocalDatabase(email: string): boolean {
    try {
        const result = runArtisanExecute(
            `echo \\App\\Models\\ClientContact::where('email', ${JSON.stringify(email)})->exists() ? 'yes' : 'no';`,
        );

        return result.includes('yes');
    } catch {
        return false;
    }
}

export function getContactPasswordResetToken(email: string): string {
    if (!contactExistsInLocalDatabase(email)) {
        throw new Error(
            `Contact ${email} is not visible to local artisan tinker (APP_URL DB mismatch).`,
        );
    }

    const token = runArtisanExecute(
        `echo optional(\\App\\Models\\ClientContact::where('email', ${JSON.stringify(email)})->first())->token;`,
    );

    if (!token || token === '' || token === 'null') {
        throw new Error(
            `Could not read a password-reset token for ${email} via artisan tinker.`,
        );
    }

    return token;
}

export function createMagicLoginUrl(email: string): string {
    if (!contactExistsInLocalDatabase(email)) {
        throw new Error(
            `Contact ${email} is not visible to local artisan tinker (APP_URL DB mismatch).`,
        );
    }

    const url = runArtisanExecute(
        `$contact = \\App\\Models\\ClientContact::where('email', ${JSON.stringify(email)})->first(); if (!$contact) { echo ''; return; } echo \\App\\Utils\\ClientPortal\\MagicLink::create($contact->email, $contact->company_id);`,
    );

    if (!url.includes('/client/magic_link/')) {
        throw new Error(
            `Could not create a magic login link for ${email}: ${url.slice(0, 200)}`,
        );
    }

    return url;
}

export function invitationSetPasswordHash(invitationKey: string): string {
    const appKey = process.env.APP_KEY ?? '';

    if (!appKey) {
        throw new Error('APP_KEY is required to build the set-password hash.');
    }

    const key = appKey.startsWith('base64:')
        ? Buffer.from(appKey.slice(7), 'base64')
        : Buffer.from(appKey);

    return createHmac('sha256', key).update(invitationKey).digest('hex');
}

export function appKeyIsAvailable(): boolean {
    return Boolean(process.env.APP_KEY) || existsSync(resolve(projectRoot, '.env'));
}
