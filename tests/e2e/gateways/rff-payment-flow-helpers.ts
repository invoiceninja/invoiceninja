import { type Page } from '@playwright/test';
import {
    assertRequiredClientInfoBlocksCheckout,
    assertRequiredClientInfoUnblocksCheckout,
    completeRequiredClientInfoForm,
} from './payment-flow-helpers';

/**
 * Asserts the required-fields form is visible and blocking gateway checkout, then
 * completes Continue and waits for the gateway container to become interactive.
 */
export async function completeRequiredClientInfoAndUnblockCheckout(
    page: Page,
): Promise<void> {
    await assertRequiredClientInfoBlocksCheckout(page);
    await completeRequiredClientInfoForm(page);
    await assertRequiredClientInfoUnblocksCheckout(page);
}
