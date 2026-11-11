import { type Page } from '@playwright/test';

// Exercise fallback branches in current browsers; this is not Safari 13 emulation.
export async function disableNativeDialogSupport(page: Page) {
    await page.addInitScript(() => {
        delete (HTMLDialogElement.prototype as any).showModal;
        delete (HTMLDialogElement.prototype as any).close;
        delete (window as any).HTMLDialogElement;
        delete (window as any).ResizeObserver;
    });
}
