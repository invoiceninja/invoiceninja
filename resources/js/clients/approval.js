import { setupDialog, openDialog, closeDialog } from './dialog';
import { createSignature } from './signature';

export function setupApproval({ signature, terms, input = false, docuninja }) {
    const button = document.getElementById('approve-button');
    if (!button) return;
    const rejectButton = document.getElementById('reject-button');
    const form = document.getElementById('approve-form');
    const inputDialog = document.getElementById('displayInputModal');
    const signatureDialog = document.getElementById('displaySignatureModal');
    const termsDialog = document.getElementById('displayTermsModal');
    const nextSignature = document.getElementById('signature-next-step');
    let drawing;
    let submitting = false;

    function submit() {
        if (submitting) return;
        submitting = true;
        form.submit();
    }

    function showTerms() {
        if (terms) return openDialog(termsDialog, button);
        else submit();
    }

    async function showSignature() {
        if (!signature) return showTerms();
        if (docuninja) {
            document.getElementById('pdf-slot-container').classList.add('hidden');
            const container = document.getElementById('docuninja-container');
            container.classList.remove('hidden');
            container.setAttribute('tabindex', '-1');
            container.focus();
            container.scrollIntoView({ block: 'start' });
            return;
        }
        if (!await openDialog(signatureDialog, button)) return;
        drawing ??= createSignature(signatureDialog.querySelector('canvas'), signed => nextSignature.disabled = !signed);
        drawing.resize();
    }

    [inputDialog, signatureDialog, termsDialog].filter(Boolean).forEach(dialog => {
        setupDialog(dialog, () => {
            if (submitting) return;
            button.disabled = false;
            if (rejectButton) rejectButton.disabled = false;
        });
    });
    button.addEventListener('click', () => {
        if (submitting || button.disabled) return;
        button.disabled = true;
        if (rejectButton) rejectButton.disabled = true;
        if (input) openDialog(inputDialog, button);
        else showSignature();
    });
    document.getElementById('input-next-step')?.addEventListener('click', () => {
        form.elements.user_input.value = document.getElementById('user_input').value;
        closeDialog(inputDialog);
        showSignature();
    });
    nextSignature.addEventListener('click', () => {
        if (!drawing || drawing.pad.isEmpty()) return;
        form.elements.signature.value = drawing.pad.toDataURL();
        closeDialog(signatureDialog);
        showTerms();
    });
    document.getElementById('clear-signature').addEventListener('click', () => drawing?.clear());
    document.getElementById('accept-terms-button').addEventListener('click', () => {
        closeDialog(termsDialog);
        submit();
    });
}
