import{w as y}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */y("#stripe-acss-authorize").then(()=>f());function f(){var l,o,d;let n;const a=(l=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:l.content,c=(o=document.querySelector('meta[name="stripe-publishable-key"]'))==null?void 0:o.content;a&&a.length>0?n=Stripe(c,{stripeAccount:a}):n=Stripe(c);const i=document.getElementById("acss-name"),s=document.getElementById("acss-email-address"),t=document.getElementById("authorize-acss"),m=(d=document.querySelector('meta[name="stripe-pi-client-secret"]'))==null?void 0:d.content,e=document.getElementById("errors");t.addEventListener("click",async u=>{u.preventDefault(),e.hidden=!0,t.disabled=!0;const h=/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;if(s.value.length<3||!s.value.match(h)){e.textContent="Please enter a valid email address.",e.hidden=!1,t.disabled=!1;return}if(i.value.length<3){e.textContent="Please enter a name for the account holder.",e.hidden=!1,t.disabled=!1;return}const{setupIntent:r,error:p}=await n.confirmAcssDebitSetup(m,{payment_method:{billing_details:{name:i.value,email:s.value}}});document.getElementById("gateway_response").value=JSON.stringify(r!=null?r:p),document.getElementById("server_response").submit()})}
