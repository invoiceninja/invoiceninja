/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

class Accept {
    constructor(displaySignature, displayTerms, docuninjaActive) {
        this.shouldDisplaySignature = displaySignature;
        this.shouldDisplayTerms = displayTerms;
        this.termsAccepted = false;
        this.docuninjaActive = docuninjaActive;
    }

    submitForm() {
        document.getElementById('approve-form').submit();
    }

    displayDocuNinja() {
        const pdfContainer = document.getElementById('pdf-slot-container');
        const docuninjaContainer = document.getElementById('docuninja-container');

        pdfContainer.classList.add('hidden');
        docuninjaContainer.classList.remove('hidden');

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

        document.getElementById("close-button").addEventListener('click', () => {
            const approveButton = document.getElementById("approve-button");

            if (approveButton)
                approveButton.disabled = false;

        });

        document
            .getElementById('approve-button')
            .addEventListener('click', () => {

                console.log("accepted ");

                if (this.shouldDisplaySignature && this.shouldDisplayTerms) {

                    if(this.docuninjaActive){
                        this.displayDocuNinja();
                        return;
                    }

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

                    if(this.docuninjaActive){
                        this.displayDocuNinja();
                        return;
                    }
                    
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

const signature = document.querySelector('meta[name="require-purchase_order-signature"]')
    .content;

const terms = document.querySelector('meta[name="show-purchase_order-terms"]').content;

const docuninja_active = document.querySelector('meta[name="docuninja-active"]').content;

new Accept(Boolean(+signature), Boolean(+terms), Boolean(+docuninja_active)).handle();
