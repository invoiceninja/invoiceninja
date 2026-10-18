import{s as l,o as i,c as s}from"./dialog-05be6357.js";/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */const e=document.getElementById("reject-button"),o=document.getElementById("displayRejectModal");if(e&&o){const t=document.getElementById("approve-button");let n=!1;l(o,()=>{n||(e.disabled=!1,t&&(t.disabled=!1))}),e.addEventListener("click",()=>{n||e.disabled||(e.disabled=!0,t&&(t.disabled=!0),i(o,e))}),document.getElementById("reject-confirm-button").addEventListener("click",()=>{if(n)return;n=!0;const d=document.getElementById("reject-form");d.elements.user_input.value=document.getElementById("reject_reason").value,s(o),d.submit()})}
