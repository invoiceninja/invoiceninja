var s=Object.defineProperty;var a=(n,e,t)=>e in n?s(n,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):n[e]=t;var r=(n,e,t)=>(a(n,typeof e!="symbol"?e+"":e,t),t);/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class i{constructor(e,t){r(this,"setupStripe",()=>(this.stripeConnect?this.stripe=Stripe(this.key,{stripeAccount:this.stripeConnect}):this.stripe=Stripe(this.key),this));r(this,"handle",()=>{document.getElementById("pay-now").addEventListener("click",e=>{let t=document.getElementById("errors");if(!document.getElementById("bancontact-name").value){t.textContent=document.querySelector("meta[name=translation-name-required]").content,t.hidden=!1,console.log("name");return}document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden"),this.stripe.confirmBancontactPayment(document.querySelector("meta[name=pi-client-secret").content,{payment_method:{billing_details:{name:document.getElementById("bancontact-name").value}},return_url:document.querySelector('meta[name="return-url"]').content})})});this.key=e,this.errors=document.getElementById("errors"),this.stripeConnect=t}}var o;const m=((o=document.querySelector('meta[name="stripe-publishable-key"]'))==null?void 0:o.content)??"";var c;const l=((c=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:c.content)??"";new i(m,l).setupStripe().handle();
