import { expect, test } from '@playwright/test';
import { resolvePlaywrightUrls } from './environment';

test('resolves API and browser URLs from one environment source', () => {
    expect(
        resolvePlaywrightUrls({
            APP_URL: 'https://api.example.test',
            VITE_API_URL: 'https://vite.example.test',
            CLIENT_PORTAL_BASE_URL: 'https://portal.example.test',
        }),
    ).toEqual({
        apiUrl: 'https://api.example.test',
        baseUrl: 'https://portal.example.test',
    });
});

test('falls back consistently when optional URLs are absent', () => {
    expect(
        resolvePlaywrightUrls({
            VITE_API_URL: 'https://vite.example.test',
        }),
    ).toEqual({
        apiUrl: 'https://vite.example.test',
        baseUrl: 'https://vite.example.test',
    });

    expect(resolvePlaywrightUrls({})).toEqual({
        apiUrl: 'http://localhost:8000',
        baseUrl: 'http://localhost:8000',
    });
});
