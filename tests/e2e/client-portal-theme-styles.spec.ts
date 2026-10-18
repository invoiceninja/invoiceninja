import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, test } from '@playwright/test';

const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = readFileSync(resolve('public/build', manifest['resources/sass/app.scss'].file), 'utf8');
const primary = readFileSync('resources/views/portal/ninja2020/components/primary-color.blade.php', 'utf8')
    .replace(/\{\{.*?\}\}/g, '#298aab');

for (const width of [390, 1280]) {
    test(`portal theme defaults and overrides at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.setContent(`${primary}<style>${css}</style>
            <div data-portal="client">
                <div data-portal-target="shell" class="bg-gray-100">
                    <header data-portal-target="header" class="bg-white">Header</header>
                    <nav data-portal-target="navigation" class="bg-white">
                        <a href="#" data-portal-target="navigation-link" class="text-gray-900 hover:bg-gray-100">Invoices</a>
                        <a href="#" data-portal-target="navigation-link" aria-current="page" class="bg-primary text-white">Payments</a>
                    </nav>
                    <button class="button button-primary bg-primary">Pay now</button>
                    <input class="input" aria-label="Name">
                    <table data-portal-target="table"><thead><tr><th class="bg-primary text-white">Number</th></tr></thead></table>
                </div>
            </div>
            <div id="outside" class="bg-primary">Outside the client portal</div>`);

        const shell = page.locator('[data-portal-target="shell"]');
        const button = page.getByRole('button', { name: 'Pay now' });
        const heading = page.locator('th');
        const navigation = page.locator('[data-portal-target="navigation"]');
        await expect(shell).toHaveCSS('background-color', 'rgb(243, 244, 246)');
        await expect(button).toHaveCSS('background-color', 'rgb(41, 138, 171)');
        await expect(button).toHaveCSS('border-radius', '4px');
        await expect(heading).toHaveCSS('color', 'rgb(255, 255, 255)');

        await page.addStyleTag({ content: `[data-portal="client"] {
            --portal-primary: #176b55;
            --portal-page-background: #edf4f1;
            --portal-surface: #f0fdf4;
            --portal-navigation-background: #173b32;
            --portal-navigation-text: #ffffff;
            --portal-navigation-hover: #285447;
            --portal-navigation-active-background: #285447;
            --portal-button-radius: 8px;
            --portal-table-header-background: #173b32;
            --portal-input-border: #176b55;
        }` });

        await expect(shell).toHaveCSS('background-color', 'rgb(237, 244, 241)');
        await expect(page.locator('header')).toHaveCSS('background-color', 'rgb(240, 253, 244)');
        await expect(navigation).toHaveCSS('background-color', 'rgb(23, 59, 50)');
        await expect(button).toHaveCSS('background-color', 'rgb(23, 107, 85)');
        await expect(button).toHaveCSS('border-radius', '8px');
        await expect(heading).toHaveCSS('background-color', 'rgb(23, 59, 50)');
        await expect(page.getByLabel('Name')).toHaveCSS('border-color', 'rgb(23, 107, 85)');
        await expect(page.locator('#outside')).toHaveCSS('background-color', 'rgb(41, 138, 171)');
        await page.getByRole('link', { name: 'Invoices' }).hover();
        await expect(page.getByRole('link', { name: 'Invoices' })).toHaveCSS('background-color', 'rgb(40, 84, 71)');
        await expect(page.locator('[aria-current="page"]')).toHaveCSS('background-color', 'rgb(40, 84, 71)');

        // Named selectors can override the theme without !important.
        await page.addStyleTag({ content: '[data-portal="client"] [data-portal-target="navigation"] { background-color: #112233; }' });
        await expect(navigation).toHaveCSS('background-color', 'rgb(17, 34, 51)');
        await button.focus();
        await expect(button).not.toHaveCSS('box-shadow', 'none');
        await button.evaluate((element: HTMLButtonElement) => { element.disabled = true; });
        await expect(button).toHaveCSS('opacity', '0.5');
    });
}
