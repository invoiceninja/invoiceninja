import{w as y}from"./authorize-credit-card-payment-bd9c9d4d.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */y("#stripe-acss-authorize").then(()=>f());function f(){var i,l,o;let n;const a=(i=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:i.content,r=(l=document.querySelector('meta[name="stripe-publishable-key"]'))==null?void 0:l.content;a&&a.length>0?n=Stripe(r,{stripeAccount:a}):n=Stripe(r);const c=document.getElementById("acss-name"),s=document.getElementById("acss-email-address"),t=document.getElementById("authorize-acss"),d=(o=document.querySelector('meta[name="stripe-pi-client-secret"]'))==null?void 0:o.content,e=document.getElementById("errors");t.addEventListener("click",async u=>{u.preventDefault(),e.hidden=!0,t.disabled=!0;const m=/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;if(s.value.length<3||!s.value.match(m)){e.textContent="Please enter a valid email address.",e.hidden=!1,t.disabled=!1;return}if(c.value.length<3){e.textContent="Please enter a name for the account holder.",e.hidden=!1,t.disabled=!1;return}const{setupIntent:p,error:h}=await n.confirmAcssDebitSetup(d,{payment_method:{billing_details:{name:c.value,email:s.value}}});document.getElementById("gateway_response").value=JSON.stringify(p??h),document.getElementById("server_response").submit()})}
