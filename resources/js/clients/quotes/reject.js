/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

import { setupDialog, openDialog, closeDialog } from '../dialog';

const button = document.getElementById('reject-button');
const dialog = document.getElementById('displayRejectModal');
if (button && dialog) {
    const approveButton = document.getElementById('approve-button');
    let submitting = false;
    setupDialog(dialog, () => {
        if (submitting) return;
        button.disabled = false;
        if (approveButton) approveButton.disabled = false;
    });
    button.addEventListener('click', () => {
        if (submitting || button.disabled) return;
        button.disabled = true;
        if (approveButton) approveButton.disabled = true;
        openDialog(dialog, button);
    });
    document.getElementById('reject-confirm-button').addEventListener('click', () => {
        if (submitting) return;
        submitting = true;
        const form = document.getElementById('reject-form');
        form.elements.user_input.value = document.getElementById('reject_reason').value;
        closeDialog(dialog);
        form.submit();
    });
}
