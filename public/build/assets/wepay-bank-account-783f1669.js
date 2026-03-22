/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */class o{initializeWePay(){var n;let t=(n=document.querySelector('meta[name="wepay-environment"]'))==null?void 0:n.content;return WePay.set_endpoint(t==="staging"?"stage":"production"),this}showBankPopup(){var t,n;WePay.bank_account.create({client_id:(t=document.querySelector("meta[name=wepay-client-id]"))==null?void 0:t.content,email:(n=document.querySelector("meta[name=contact-email]"))==null?void 0:n.content,options:{avoidMicrodeposits:!0}},function(e){e.error?(errors.textContent="",errors.textContent=e.error_description,errors.hidden=!1):(document.querySelector('input[name="bank_account_id"]').value=e.bank_account_id,document.getElementById("server_response").submit())},function(e){e.error&&(errors.textContent="",errors.textContent=e.error_description,errors.hidden=!1)})}handle(){this.initializeWePay().showBankPopup()}}document.addEventListener("DOMContentLoaded",()=>{new o().handle()});
