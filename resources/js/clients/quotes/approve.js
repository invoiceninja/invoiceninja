/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

class Approve {
    constructor(displaySignature, displayTerms) {
        this.shouldDisplaySignature = displaySignature;
        this.shouldDisplayTerms = displayTerms;
        this.termsAccepted = false;
    }

    submitForm() {
        document.getElementById('approve-form').submit();
    }

    displaySignature() {
        let displaySignatureModal = document.getElementById(
            'displaySignatureModal'
        );
        displaySignatureModal.removeAttribute('style');

        const signaturePad = new SignaturePad(
            document.getElementById('signature-pad'),
            {
                penColor: 'rgb(0, 0, 0)',
            }
        );

        signaturePad.onEnd = function(){  
            document.getElementById("signature-next-step").disabled = false;
        };


        this.signaturePad = signaturePad;
    }

    displayTerms() {
        let displayTermsModal = document.getElementById("displayTermsModal");
        displayTermsModal.removeAttribute("style");
    }

    handle() {

        document.getElementById("signature-next-step").disabled = true;
        document.getElementById("close_button").addEventListener('click', () => {
            const approveButton = document.getElementById("approve-button");

            if(approveButton)
                approveButton.disabled = false;

        });

        document
            .getElementById('approve-button')
            .addEventListener('click', () => {
                if (this.shouldDisplaySignature && this.shouldDisplayTerms) {
                    this.displaySignature();

                    document
                        .getElementById('signature-next-step')
                        .addEventListener('click', () => {
                            this.displayTerms();

                            document
                                .getElementById('accept-terms-button')
                                .addEventListener('click', () => {
                                    document.querySelector(
                                        'input[name="signature"'
                                    ).value = this.signaturePad.toDataURL();
                                    this.termsAccepted = true;
                                    this.submitForm();
                                });
                        });
                }

                if (this.shouldDisplaySignature && !this.shouldDisplayTerms) {
                    this.displaySignature();

                    document
                        .getElementById('signature-next-step')
                        .addEventListener('click', () => {
                            document.querySelector(
                                'input[name="signature"'
                            ).value = this.signaturePad.toDataURL();
                            this.submitForm();
                        });
                }

                if (!this.shouldDisplaySignature && this.shouldDisplayTerms) {
                    this.displayTerms();

                    document
                        .getElementById('accept-terms-button')
                        .addEventListener('click', () => {
                            this.termsAccepted = true;
                            this.submitForm();
                        });
                }

                if (!this.shouldDisplaySignature && !this.shouldDisplayTerms) {
                    this.submitForm();
                }
            });
    }
}

const signature = document.querySelector('meta[name="require-quote-signature"]')
    .content;

const terms = document.querySelector('meta[name="show-quote-terms"]').content;

new Approve(Boolean(+signature), Boolean(+terms)).handle();
