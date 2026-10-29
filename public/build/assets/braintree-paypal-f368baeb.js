import{m as r,B as o}from"./payment-53f57dcc.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */class h extends o{setBusy(s){var t;super.setBusy(s),(t=this.find("#paypal-spinner"))==null||t.classList.toggle("hidden",!s)}async handle(){this.setBusy(!0),this.bindSelection("#paypal-button");const s=await this.createClient(),t=await this.keep(await window.braintree.paypalCheckout.create({client:s}));if(await t.loadPayPalSDK({vault:!0}),!this.root.isConnected)return;const n=window.paypal.Buttons({fundingSource:window.paypal.FUNDING.PAYPAL,createBillingAgreement:async()=>{this.setBusy(!0);try{return await t.createPayment({flow:"vault"})}catch(e){throw this.setBusy(!1),e}},onApprove:async e=>{if(!(this.submitted||this.approving)){this.approving=!0,this.setBusy(!0);try{const a=await t.tokenizePayment(e);this.submitted=!0,this.submit(a)}catch(a){this.approving=!1,this.showError(a),this.setBusy(!1)}}},onCancel:()=>this.setBusy(!1),onError:e=>{this.showError(e),this.setBusy(!1)}});this.instances.push({teardown:()=>n.close()}),await n.render(this.find("#paypal-button")),this.on(this.find("#pay-now-with-token"),"click",()=>{this.busy||(this.setBusy(!0),this.submit())}),this.setBusy(!1)}}r("#braintree-paypal-payment",i=>new h(i));
