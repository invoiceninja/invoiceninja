/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class Approve {
    constructor(displaySignature) {
        this.shouldDisplaySignature = displaySignature;
    }

    submitForm() {
        document.getElementById('approve-form').submit();
    }

    displaySignature() {
        let displaySignatureModal = document.getElementById('displaySignatureModal');
        displaySignatureModal.removeAttribute('style');

        const signaturePad = new SignaturePad(document.getElementById('signature-pad'), {
            backgroundColor: 'rgb(240,240,240)',
            penColor: 'rgb(0, 0, 0)'
        });
    }

    handle() {
        document.getElementById('approve-button').addEventListener('click', () => {
            if (this.shouldDisplaySignature) {
                this.displaySignature();

                document.getElementById('signature-next-step').addEventListener('click', () => {
                    this.submitForm();
                });
            }

            if (!this.shouldDisplaySignature) this.submitForm();
        })
    }
}

const signature = document.querySelector(
    'meta[name="require-quote-signature"]'
).content;

new Approve(Boolean(+signature)).handle();


