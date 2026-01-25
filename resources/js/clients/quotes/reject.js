/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

class Reject {
    constructor() {
    }

    submitForm() {
        document.getElementById('reject-form').submit();
    }

    displayRejectModal() {
        let displayRejectModal = document.getElementById("displayRejectModal");
        if (displayRejectModal) {
            displayRejectModal.removeAttribute("style");
        }
    }

    hideRejectModal() {
        let displayRejectModal = document.getElementById("displayRejectModal");
        if (displayRejectModal) {
            displayRejectModal.style.display = 'none';
        }
    }

    handle() {
        const rejectButton = document.getElementById('reject-button');
        if (!rejectButton) return;

        rejectButton.addEventListener('click', () => {
            rejectButton.disabled = true;
            
            // Re-enable the reject button after 2 seconds (Rehabilitar botón de rechazo después de 2 segundos)
            setTimeout(() => {
                rejectButton.disabled = false;
            }, 2000);

            this.displayRejectModal();
        });

        const rejectConfirmButton = document.getElementById('reject-confirm-button');
        if (rejectConfirmButton) {
            rejectConfirmButton.addEventListener('click', () => {
                const rejectReason = document.getElementById('reject_reason');
                
                if (rejectReason) {
                    const userInputField = document.querySelector('#reject-form input[name="user_input"]');
                    if (userInputField) {
                        userInputField.value = rejectReason.value;
                    }
                }

                this.hideRejectModal();
                this.submitForm();
            });
        }

        const rejectCloseButton = document.getElementById('reject-close-button');
        if (rejectCloseButton) {
            rejectCloseButton.addEventListener('click', () => {
                this.hideRejectModal();
            });
        }

        // Handle backdrop click to close modal (Clic en fondo para cerrar modal)
        const rejectModalBackdrop = document.getElementById('reject-modal-backdrop');
        if (rejectModalBackdrop) {
            rejectModalBackdrop.addEventListener('click', () => {
                this.hideRejectModal();
            });
        }
    }
}

new Reject().handle();
