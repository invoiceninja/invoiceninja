let polyfill;
const dialogs = new WeakMap();

function syncScrollLock() {
    document.documentElement.classList.toggle('portal-dialog-open', !!document.querySelector('.portal-dialog[open]'));
}

function stateFor(dialog) {
    if (!dialogs.has(dialog)) {
        const state = { request: 0, pending: null, opener: null };
        dialogs.set(dialog, state);
        dialog.addEventListener('close', () => {
            syncScrollLock();
            // Cancellation handlers must re-enable the trigger before focus returns.
            Promise.resolve().then(() => {
                if (!document.querySelector('.portal-dialog[open]') && state.opener?.isConnected) {
                    state.opener.focus({ preventScroll: true });
                }
            });
        });
    }
    return dialogs.get(dialog);
}

export function openDialog(dialog, opener = document.activeElement) {
    const state = stateFor(dialog);
    if (dialog.open) return Promise.resolve(true);
    if (state.pending) return state.pending;
    const request = ++state.request;
    state.opener = opener;
    state.pending = (async () => {
        try {
            if (typeof dialog.showModal !== 'function') {
                polyfill ??= import('dialog-polyfill').catch(error => {
                    polyfill = null;
                    throw error;
                });
                const { default: fallback } = await polyfill;
                if (request !== state.request || !dialog.isConnected) return false;
                fallback.registerDialog(dialog);
                dialog.classList.add('portal-dialog-fallback');
            }
            if (request !== state.request || !dialog.isConnected) return false;
            dialog.showModal();
            syncScrollLock();
            return true;
        } catch (_) {
            closeDialog(dialog);
            dialog.dispatchEvent(new Event('cancel'));
            state.opener?.focus({ preventScroll: true });
            window.alert(dialog.dataset.errorMessage);
            return false;
        }
    })();
    state.pending.finally(() => { state.pending = null; });
    return state.pending;
}

export function closeDialog(dialog) {
    if (!dialog) return;
    const state = stateFor(dialog);
    state.request++;
    if (dialog.open) dialog.close();
    syncScrollLock();
}

export function dialogComponent() {
    return {
        open(event) { return openDialog(this.$refs.dialog, event?.currentTarget); },
        close() { closeDialog(this.$refs.dialog); },
        destroy() { closeDialog(this.$refs.dialog); },
    };
}

export function setupDialog(dialog, onCancel = () => {}) {
    stateFor(dialog);
    dialog.querySelectorAll('[data-dialog-close]').forEach(button => {
        button.addEventListener('click', () => {
            closeDialog(dialog);
            onCancel();
        });
    });
    dialog.addEventListener('cancel', onCancel);
}
