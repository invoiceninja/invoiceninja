import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

export interface PlaywrightUrls {
    apiUrl: string;
    baseUrl: string;
}

export function loadPlaywrightEnvironment(): void {
    for (const envFile of ['.env', '.env.testing', '.env.playwright']) {
        const path = resolve(projectRoot, envFile);

        if (existsSync(path)) {
            process.loadEnvFile(path);
        }
    }
}

export function resolvePlaywrightUrls(
    environment: NodeJS.ProcessEnv = process.env,
): PlaywrightUrls {
    const apiUrl =
        environment.APP_URL ??
        environment.VITE_API_URL ??
        'http://localhost:8000';

    return {
        apiUrl,
        baseUrl: environment.CLIENT_PORTAL_BASE_URL ?? apiUrl,
    };
}
