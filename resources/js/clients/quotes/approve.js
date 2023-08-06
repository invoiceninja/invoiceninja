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
    constructor(displaySignature, displayTerms, userInput) {
        this.shouldDisplaySignature = displaySignature;
        this.shouldDisplayTerms = displayTerms;
        this.shouldDisplayUserInput = userInput;
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

    displayInput() {
        let displayInputModal = document.getElementById("displayInputModal");
        displayInputModal.removeAttribute("style");
    }

    handle() {

        document.getElementById("signature-next-step").disabled = true;

        document.getElementById("close-button").addEventListener('click', () => {
            const approveButton = document.getElementById("approve-button");

            console.log('close button');

            if(approveButton)
                approveButton.disabled = false;

        });

        document.getElementById("close-terms-button").addEventListener('click', () => {
            const approveButton = document.getElementById("approve-button");

            console.log('close terms-button');

            if (approveButton)
                approveButton.disabled = false;

        });


        document
            .getElementById('approve-button')
            .addEventListener('click', () => {

                if (!this.shouldDisplaySignature && !this.shouldDisplayTerms && this.shouldDisplayUserInput){
                    this.displayInput();

                        document
                            .getElementById('input-next-step')
                            .addEventListener('click', () => {
                                document.querySelector(
                                    'input[name="user_input"'
                                ).value = document.getElementById('user_input').value;
                                this.termsAccepted = true;
                                this.submitForm();
                            });
                            
                }

                if(this.shouldDisplayUserInput)
                    this.displayInput();


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
                                    document.querySelector(
                                        'input[name="user_input"'
                                    ).value = document.getElementById('user_input').value;
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
                            document.querySelector(
                                'input[name="user_input"'
                            ).value = document.getElementById('user_input').value;
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

                if (!this.shouldDisplaySignature && !this.shouldDisplayTerms && !this.shouldDisplayUserInput) {
                    this.submitForm();
                }
            });
    }
}

const signature = document.querySelector('meta[name="require-quote-signature"]')
    .content;

const terms = document.querySelector('meta[name="show-quote-terms"]').content;

const user_input = document.querySelector('meta[name="accept-user-input"]').content;

new Approve(Boolean(+signature), Boolean(+terms), Boolean(+user_input)).handle();
