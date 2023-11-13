/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class n{constructor(){this.parentElement=document.querySelector(".form-check-parent"),this.parentForm=document.getElementById("bulkActions")}watchCheckboxes(e){document.querySelectorAll(".child-hidden-input").forEach(t=>t.remove()),document.querySelectorAll(".form-check-child").forEach(t=>{e.checked?(t.checked=e.checked,this.processChildItem(t,document.getElementById("bulkActions"))):(t.checked=!1,document.querySelectorAll(".child-hidden-input").forEach(l=>l.remove()))})}processChildItem(e,t,l={}){if(l.hasOwnProperty("single")&&document.querySelectorAll(".child-hidden-input").forEach(r=>r.remove()),e.checked===!1){let r=document.querySelectorAll("input.child-hidden-input");for(let d of r)d.value==e.dataset.value&&d.remove();return}let c=document.createElement("INPUT");c.setAttribute("name","quotes[]"),c.setAttribute("value",e.dataset.value),c.setAttribute("class","child-hidden-input"),c.hidden=!0,t.append(c)}handle(){this.parentElement.addEventListener("click",()=>{this.watchCheckboxes(this.parentElement)});for(let e of document.querySelectorAll(".form-check-child"))e.addEventListener("click",()=>{this.processChildItem(e,this.parentForm)})}}new n().handle();
