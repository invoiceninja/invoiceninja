var s=Object.defineProperty;var c=(n,e,t)=>e in n?s(n,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):n[e]=t;var r=(n,e,t)=>(c(n,typeof e!="symbol"?e+"":e,t),t);import{w as i}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class u{constructor(e,t){r(this,"setupStripe",()=>(this.stripeConnect?this.stripe=Stripe(this.key,{stripeAccount:this.stripeConnect}):this.stripe=Stripe(this.key),this));r(this,"handle",()=>{document.getElementById("pay-now").addEventListener("click",e=>{document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden"),this.stripe.confirmSofortPayment(document.querySelector("meta[name=pi-client-secret").content,{payment_method:{sofort:{country:document.querySelector('meta[name="country"]').content}},return_url:document.querySelector('meta[name="return-url"]').content})})});this.key=e,this.errors=document.getElementById("errors"),this.stripeConnect=t}}i("#stripe-sofort-payment").then(()=>{var t,o;const n=((t=document.querySelector('meta[name="stripe-publishable-key"]'))==null?void 0:t.content)??"",e=((o=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:o.content)??"";new u(n,e).setupStripe().handle()});
