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
import { createSignature } from '../signature';

const form = document.getElementById('payment-form');
const enabled = name => Boolean(+document.querySelector(`meta[name="${name}"]`)?.content);
const signatureDialog = document.getElementById('displaySignatureModal');
const nextSignature = document.getElementById('signature-next-step');
const fields = {
    rff_first_name: 'contact_first_name',
    rff_last_name: 'contact_last_name',
    rff_email: 'contact_email',
    rff_city: 'client_city',
    rff_postal_code: 'client_postal_code',
};
let steps = [];
let drawing;
let submitting = false;
let selectedMethod;

async function advance() {
    if (steps.length) {
        const dialog = document.getElementById(steps[0]);
        if (!await openDialog(dialog, selectedMethod)) return;
        if (dialog === signatureDialog) {
            drawing ??= createSignature(dialog.querySelector('canvas'), signed => nextSignature.disabled = !signed);
            drawing.resize();
        }
    } else if (!submitting) {
        submitting = true;
        form.submit();
    }
}

function next() {
    closeDialog(document.getElementById(steps.shift()));
    advance();
}

['displayRequiredFieldsModal', 'displaySignatureModal', 'displayTermsModal'].forEach(id => {
    setupDialog(document.getElementById(id), () => {
        steps = [];
        selectedMethod?.focus();
    });
});

document.querySelectorAll('.dropdown-gateway-button').forEach(button => {
    button.addEventListener('click', event => {
        event.preventDefault();
        if (submitting || steps.length) return;
        selectedMethod = button;
        form.elements.company_gateway_id.value = button.dataset.companyGatewayId;
        form.elements.payment_method_id.value = button.dataset.gatewayTypeId;
        const missingFields = Object.values(fields).some(name => !form.elements[name].value.trim());
        steps = [];
        if (button.dataset.isPaypal === '1' && missingFields) steps.push('displayRequiredFieldsModal');
        if (enabled('require-invoice-signature')) steps.push('displaySignatureModal');
        if (enabled('show-invoice-terms')) steps.push('displayTermsModal');
        advance();
    });
});
document.getElementById('rff-next-step').addEventListener('click', () => {
    if (steps[0] !== 'displayRequiredFieldsModal') return;
    Object.entries(fields).forEach(([source, target]) => {
        const input = document.querySelector(`input[name="${source}"]`);
        if (input) form.elements[target].value = input.value;
    });
    next();
});
nextSignature.addEventListener('click', () => {
    if (steps[0] !== 'displaySignatureModal' || !drawing || drawing.pad.isEmpty()) return;
    form.elements.signature.value = drawing.pad.toDataURL();
    next();
});
document.getElementById('clear-signature').addEventListener('click', () => drawing?.clear());
document.getElementById('accept-terms-button').addEventListener('click', () => {
    if (steps[0] === 'displayTermsModal') next();
});
