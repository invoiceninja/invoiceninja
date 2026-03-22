/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class l{constructor(){}submitForm(){document.getElementById("reject-form").submit()}displayRejectModal(){let e=document.getElementById("displayRejectModal");e&&e.removeAttribute("style")}hideRejectModal(){let e=document.getElementById("displayRejectModal");e&&(e.style.display="none")}handle(){const e=document.getElementById("reject-button");if(!e)return;e.addEventListener("click",()=>{e.disabled=!0,setTimeout(()=>{e.disabled=!1},2e3),this.displayRejectModal()});const t=document.getElementById("reject-confirm-button");t&&t.addEventListener("click",()=>{const n=document.getElementById("reject_reason");if(n){const o=document.querySelector('#reject-form input[name="user_input"]');o&&(o.value=n.value)}this.hideRejectModal(),this.submitForm()});const d=document.getElementById("reject-close-button");d&&d.addEventListener("click",()=>{this.hideRejectModal()});const c=document.getElementById("reject-modal-backdrop");c&&c.addEventListener("click",()=>{this.hideRejectModal()})}}new l().handle();
