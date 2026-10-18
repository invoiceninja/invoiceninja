import{i as y,w as f}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */y()?m():f("#stripe-bank-transfer-payment").then(()=>m());function m(){var r,o,s,a;const i=(r=document.querySelector('meta[name="stripe-client-secret"]'))==null?void 0:r.content,d=(o=document.querySelector('meta[name="stripe-return-url"]'))==null?void 0:o.content,l={clientSecret:i,appearance:{theme:"stripe",variables:{colorPrimary:"#0570de",colorBackground:"#ffffff",colorText:"#30313d",colorDanger:"#df1b41",fontFamily:"Ideal Sans, system-ui, sans-serif",spacingUnit:"2px",borderRadius:"4px"}}},e=Stripe(document.querySelector('meta[name="stripe-publishable-key"]').getAttribute("content")),t=(a=(s=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:s.content)!=null?a:"";t&&(e.stripeAccount=t);const n=e.elements(l);n.create("payment").mount("#payment-element"),document.getElementById("payment-form").addEventListener("submit",async u=>{u.preventDefault(),document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.add("hidden"),document.querySelector("#pay-now > span").classList.remove("hidden");const{error:c}=await e.confirmPayment({elements:n,confirmParams:{return_url:d}});if(c){document.getElementById("pay-now").disabled=!1,document.querySelector("svg").classList.remove("hidden"),document.querySelector("span").classList.add("hidden");const p=document.querySelector("#errors");p.textContent=c.message}})}
