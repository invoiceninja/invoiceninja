import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import postcss from 'postcss';
import { expect, test } from '@playwright/test';

const root = resolve('resources/views/portal/ninja2020');
function templates(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) =>
        entry.isDirectory()
            ? templates(resolve(directory, entry.name))
            : entry.name.endsWith('.blade.php') ? [resolve(directory, entry.name)] : [],
    );
}
const sources = templates(root).map((file) => ({
    file,
    source: readFileSync(file, 'utf8').replace(/\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->/g, ''),
}));
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = readFileSync(resolve('public/build', manifest['resources/sass/app.scss'].file), 'utf8');

// Check every portal template, including conditional/provider views that a single
// account cannot render. Keep this independent of a hand-maintained button list.
test('portal interaction rules never alter typography or layout', async () => {
    const violations: string[] = [];
    const geometry = /^(font(?:-weight|-size|-family|-stretch)?|line-height|letter-spacing|padding(?:-.+)?|margin(?:-.+)?|(?:min-|max-)?(?:width|height)|border(?:-(?:top|right|bottom|left))?-width|transform|scale|translate)$/;
    const checkCss = (text: string, file: string) => {
        postcss.parse(text, { from: file }).walkRules((rule) => {
            if (!/:(hover|focus|focus-visible|focus-within)\b/.test(rule.selector)) return;
            rule.walkDecls((declaration) => {
                if (geometry.test(declaration.prop)) {
                    violations.push(`${file}: ${rule.selector}: ${declaration.prop}`);
                }
            });
        });
    };
    checkCss(css, 'compiled portal CSS');
    for (const { file, source } of sources) {
        for (const style of source.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)) {
            checkCss(style[1].replace(/\{!![\s\S]*?!!\}/g, '').replace(/\{\{[\s\S]*?\}\}/g, 'initial'), file);
        }
        const unsafe = source.match(/\b(?:hover|focus|focus-visible|focus-within):(?:font-\S+|text-(?:xs|sm|base|lg|\d?xl)\b|(?:p[xytrbl]?|m[xytrbl]?|w|h|scale|translate-[xy])-\S+)/g);
        if (unsafe) violations.push(`${file}: ${unsafe.join(', ')}`);
    }
    expect(violations).toEqual([]);
});

// Render every explicit button/action class pattern, plus both conditional
// sidebar/tab states. These are CSS contracts, not substitutes for workflow tests.
const controls = new Map<string, { file: string; tag: string; classes: string }>();
for (const { file, source } of sources) {
    for (const match of source.matchAll(/(?<![:\w-])class="([^"]*)"/g)) {
        const opening = source.slice(source.lastIndexOf('<', match.index), match.index);
        const tag = opening.match(/^<(button|a|input)\b/)?.[1];
        if (!tag || (tag === 'input' && !/type="(?:submit|button|reset)"/.test(opening))) continue;
        if (tag === 'a' && !/\bbutton|rounded|group flex items-center p-4/.test(match[1])) continue;
        for (const active of [false, true]) {
            const classes = match[1].replace(/\{\{([\s\S]*?)\}\}/g, (_, expression: string) => {
                const branches = expression.match(/\?\s*'([^']*)'\s*:\s*'([^']*)'/);
                return branches ? branches[active ? 1 : 2] : '';
            }).trim();
            if (!classes || /(?:^|\s)(?:hidden|sr-only)(?:\s|$)/.test(classes)) continue;
            controls.set(`${tag}:${classes}`, { file, tag, classes });
        }
    }
}

for (const width of [390, 1280]) {
    test(`all portal button class patterns keep stable geometry at ${width}px`, async ({ page }, testInfo) => {
        test.setTimeout(180_000);
        await page.setViewportSize({ width, height: 900 });
        await page.setContent('<main></main>');
        await page.addStyleTag({ content: css });
        await page.addStyleTag({ content: ':root { --primary-color: #008060; } .bg-primary { background-color: var(--primary-color); } * { transition: none !important; }' });
        const inventory = [...controls.values()];
        await testInfo.attach('button-class-inventory', { body: JSON.stringify(inventory, null, 2), contentType: 'application/json' });
        for (const control of inventory) {
            await test.step(`${control.file.replace(root, '')}: ${control.classes}`, async () => {
                await page.locator('main').evaluate((main, { tag, classes }) => {
                    main.replaceChildren();
                    const element = document.createElement(tag);
                    element.id = 'control';
                    element.className = classes;
                    if (element instanceof HTMLAnchorElement) element.href = '#';
                    if (element instanceof HTMLInputElement) { element.type = 'submit'; element.value = 'Approve quote'; }
                    else element.textContent = 'Approve quote';
                    main.append(element);
                }, control);
                const element = page.locator('#control');
                if (!(await element.isVisible())) {
                    expect(control.classes).toMatch(/(?:sm|md|lg|xl):hidden/);
                    return; // The mobile-menu toggle is exercised at 390px.
                }
                const metrics = () => element.evaluate((el) => {
                    const rect = el.getBoundingClientRect(), style = getComputedStyle(el);
                    return { width: rect.width, height: rect.height, weight: style.fontWeight, size: style.fontSize, spacing: style.letterSpacing };
                });
                await page.mouse.move(width - 1, 899);
                const before = await metrics();
                await element.hover({ force: true });
                expect(await metrics()).toEqual(before);
                await page.keyboard.press('Tab');
                await element.focus();
                expect(await metrics()).toEqual(before);
            });
        }
    });
}

for (const color of ['#1c64f2', '#008060']) {
    test(`shared variants have visible feedback and respect disabled state (${color})`, async ({ page }) => {
        await page.setContent(`<style>${css}</style><style>:root{--primary-color:${color}}.bg-primary{background-color:var(--primary-color)}*{transition:none!important}</style>`);
        for (const variant of ['button-primary bg-primary', 'button-primary bg-blue-600', 'button-secondary', 'button-danger', 'button-link']) {
            await page.evaluate((classes) => {
                const button = document.createElement('button');
                button.className = `button ${classes}`;
                button.textContent = 'Approve';
                document.body.replaceChildren(button);
            }, variant);
            const button = page.locator('button');
            const appearance = () => button.evaluate((el) => {
                const s = getComputedStyle(el);
                return [s.backgroundColor, s.backgroundImage, s.color, s.textDecorationLine];
            });
            await page.mouse.move(900, 800);
            const before = await appearance();
            await button.hover();
            expect(await appearance(), variant).not.toEqual(before);
            await page.keyboard.press('Tab');
            await button.focus();
            expect(await button.evaluate((el) => getComputedStyle(el).boxShadow), variant).not.toBe('none');
            await button.evaluate((el) => { el.blur(); el.disabled = true; });
            await page.mouse.move(900, 800);
            const disabled = await appearance();
            await button.hover({ force: true });
            expect(await appearance(), variant).toEqual(disabled);
        }
    });
}

for (const [file, id, classes] of [
    ['gateways/checkout/credit_card/includes/styles.blade.php', 'pay-button', ''],
    ['gateways/payware/pay.blade.php', 'copy-id', 'payware-copy-btn'],
]) {
    test(`locally styled gateway control stays stable: ${id}`, async ({ page }) => {
        const source = readFileSync(resolve(root, file), 'utf8');
        const styles = [...source.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)].map((match) => match[1]).join('\n');
        await page.setContent(`<style>${css}</style><style>${styles}</style><button id="${id}" class="${classes}">Pay now</button>`);
        const button = page.locator('button');
        const metrics = () => button.evaluate((el) => {
            const r = el.getBoundingClientRect(), s = getComputedStyle(el);
            return [r.width, r.height, s.fontWeight, s.fontSize];
        });
        const before = await metrics();
        await button.hover();
        expect(await metrics()).toEqual(before);
        await page.keyboard.press('Tab');
        await button.focus();
        expect(await metrics()).toEqual(before);
        expect(await button.evaluate((el) => getComputedStyle(el).boxShadow)).not.toBe('none');
    });
}
