import { expect, test, type Page } from '@playwright/test';
import { replacePayPalLoginPassword } from './client-portal-payments/paypal/flow-helpers';

async function openPasswordForm(page: Page, behavior: string): Promise<void> {
    await page.setContent('<main></main>');
    await page.evaluate((mode) => {
        const form_markup =
            '<form id="login"><input id="password" name="login_password" type="password"><button id="btnLogin" type="submit">Log In</button></form>';
        (window as any).login_attempts = [];
        document.body.innerHTML = form_markup;
        document.addEventListener('submit', (event) => {
            event.preventDefault();
            const form = event.target as HTMLFormElement;
            const attempts = (window as any).login_attempts as string[];
            attempts.push(
                form.querySelector<HTMLInputElement>('#password')!.value
            );
            if (
                mode === 'always-empty' ||
                (mode === 'replace-once' && attempts.length === 1)
            ) {
                document.body.innerHTML =
                    form_markup + '<p role="alert">Enter your password.</p>';
                return;
            }
            document.body.innerHTML =
                '<button id="confirmButtonTop">Pay Now</button>';
        });
    }, behavior);
}

test('PayPal login submits a populated password normally', async ({ page }) => {
    await openPasswordForm(page, 'normal');
    await replacePayPalLoginPassword(page, 'fixture-password');
    expect(await page.evaluate(() => (window as any).login_attempts)).toEqual([
        'fixture-password',
    ]);
});

test('PayPal login re-enters the password when the vault flow replaces its form', async ({
    page,
}) => {
    await openPasswordForm(page, 'replace-once');
    await replacePayPalLoginPassword(page, 'fixture-password');
    expect(await page.evaluate(() => (window as any).login_attempts)).toEqual([
        'fixture-password',
        'fixture-password',
    ]);
});

test('PayPal login stops after one empty-form retry', async ({ page }) => {
    await openPasswordForm(page, 'always-empty');
    await expect(
        replacePayPalLoginPassword(page, 'fixture-password')
    ).rejects.toThrow(
        'PayPal sandbox submitted an empty password after re-entering it.'
    );
    expect(
        await page.evaluate(() => (window as any).login_attempts)
    ).toHaveLength(2);
});
