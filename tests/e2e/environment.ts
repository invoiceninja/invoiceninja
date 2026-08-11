import { existsSync } from 'node:fs';

export interface PlaywrightUrls {
    apiUrl: string;
    baseUrl: string;
}

export function loadPlaywrightEnvironment(): void {
    const envFile = '.env';

    if (existsSync(envFile)) {
        process.loadEnvFile(envFile);
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
