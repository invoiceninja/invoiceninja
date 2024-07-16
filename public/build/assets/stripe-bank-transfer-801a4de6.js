import{w as u}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */u("#stripe-bank-transfer-payment").then(()=>p());function p(){var r,o,s;const c=(r=document.querySelector('meta[name="stripe-client-secret"]'))==null?void 0:r.content,m=(o=document.querySelector('meta[name="stripe-return-url"]'))==null?void 0:o.content,i={clientSecret:c,appearance:{theme:"stripe",variables:{colorPrimary:"#0570de",colorBackground:"#ffffff",colorText:"#30313d",colorDanger:"#df1b41",fontFamily:"Ideal Sans, system-ui, sans-serif",spacingUnit:"2px",borderRadius:"4px"}}},e=Stripe(document.querySelector('meta[name="stripe-publishable-key"]').getAttribute("content")),t=((s=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:s.content)??"";t&&(e.stripeAccount=t);const n=e.elements(i);n.create("payment").mount("#payment-element"),document.getElementById("payment-form").addEventListener("submit",async d=>{d.preventDefault(),document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.add("hidden"),document.querySelector("#pay-now > span").classList.remove("hidden");const{error:a}=await e.confirmPayment({elements:n,confirmParams:{return_url:m}});if(a){document.getElementById("pay-now").disabled=!1,document.querySelector("svg").classList.remove("hidden"),document.querySelector("span").classList.add("hidden");const l=document.querySelector("#errors");l.textContent=a.message}})}
