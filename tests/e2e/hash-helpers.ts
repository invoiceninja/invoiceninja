import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const decodedIds = new Map<string, string>();

function projectRoot(): string {
    // Playwright loads these helpers as ESM, so __dirname is unavailable.
    return path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
}

/**
 * Decode an API hashed primary key to the raw integer id the portal dropdown
 * uses in `data-company-gateway-id`. Uses the same Hashids salt/length as
 * `MakesHash` (HASH_SALT, min length 10).
 */
export function decodePrimaryKey(hashedId: string): string {
    const cached = decodedIds.get(hashedId);

    if (cached) {
        return cached;
    }

    const root = projectRoot();
    const autoload = path.join(root, 'vendor/autoload.php');

    if (!existsSync(autoload)) {
        throw new Error(
            `Cannot decode primary key ${hashedId}: ${autoload} is missing`,
        );
    }

    const salt = process.env.HASH_SALT ?? '';
    const php = `
        require ${JSON.stringify(autoload)};
        $hashids = new Hashids\\Hashids(${JSON.stringify(salt)}, 10);
        $decoded = $hashids->decode(${JSON.stringify(hashedId)});
        echo $decoded[0] ?? '';
    `;

    const raw = execFileSync('php', ['-r', php], {
        encoding: 'utf8',
        cwd: root,
    }).trim();

    if (!raw) {
        throw new Error(`Failed to decode primary key: ${hashedId}`);
    }

    decodedIds.set(hashedId, raw);

    return raw;
}
