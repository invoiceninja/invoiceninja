/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */function i(...t){return new Promise(n=>{if(!t.length){n([]);return}const e=t.map(d=>document.querySelector(d)).filter(Boolean);if(e.length===t.length){n(e);return}const o=new MutationObserver(()=>{const d=t.map(a=>document.querySelector(a)).filter(Boolean);d.length===t.length&&(o.disconnect(),n(d))});o.observe(document.body,{childList:!0,subtree:!0})})}function c(){const t=document.querySelector('meta[name="instant-payment"]');return!!(t&&t instanceof HTMLMetaElement&&t.content==="yes")}/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class r{constructor(){this.tokens=[]}mountFrames(){console.log("Mount checkout frames..")}handlePaymentUsingToken(n){document.getElementById("checkout--container").classList.add("hidden"),document.getElementById("pay-now-with-token--container").classList.remove("hidden"),document.getElementById("save-card--container").style.display="none",document.querySelector("input[name=token]").value=n.target.dataset.token}handlePaymentUsingCreditCard(n){document.getElementById("checkout--container").classList.remove("hidden"),document.getElementById("pay-now-with-token--container").classList.add("hidden"),document.getElementById("save-card--container").style.display="grid",document.querySelector("input[name=token]").value="";const e=document.getElementById("pay-button"),o=document.querySelector('meta[name="public-key"]').content??"",d=document.getElementById("payment-form");Frames.init(o),Frames.addEventHandler(Frames.Events.CARD_VALIDATION_CHANGED,function(a){e.disabled=!Frames.isCardValid()}),Frames.addEventHandler(Frames.Events.CARD_TOKENIZATION_FAILED,function(a){e.disabled=!1}),Frames.addEventHandler(Frames.Events.CARD_TOKENIZED,function(a){e.disabled=!0,document.querySelector('input[name="gateway_response"]').value=JSON.stringify(a),document.querySelector('input[name="store_card"]').value=document.querySelector("input[name=token-billing-checkbox]:checked").value,document.getElementById("server-response").submit()}),d.addEventListener("submit",function(a){a.preventDefault(),e.disabled=!0,Frames.submitCard()})}completePaymentUsingToken(n){let e=document.getElementById("pay-now-with-token");e.disabled=!0,e.querySelector("svg").classList.remove("hidden"),e.querySelector("span").classList.add("hidden"),document.getElementById("server-response").submit()}handle(){this.handlePaymentUsingCreditCard(),Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach(n=>n.addEventListener("click",this.handlePaymentUsingToken)),document.getElementById("toggle-payment-with-credit-card").addEventListener("click",this.handlePaymentUsingCreditCard),document.getElementById("pay-now-with-token").addEventListener("click",this.completePaymentUsingToken)}}function s(){new r().handle()}c()?s():i("#checkout-credit-card-payment").then(()=>new r().handle());
