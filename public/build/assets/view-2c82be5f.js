/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */class a{constructor(){this.url=new URL(document.querySelector("meta[name=pdf-url]").content),this.startDate="",this.endDate="",this.showPaymentsTable=!1,this.showAgingTable=!1,this.status=""}bindEventListeners(){["#date-from","#date-to","#show-payments-table","#show-aging-table","#status"].forEach(e=>{document.querySelector(e).addEventListener("change",t=>this.handleValueChange(t))})}handleValueChange(e){e.target.type==="checkbox"?this[e.target.dataset.field]=e.target.checked:this[e.target.dataset.field]=e.target.value,this.updatePdf()}get composedUrl(){return this.url.search="",this.startDate.length>0&&this.url.searchParams.append("start_date",this.startDate),this.endDate.length>0&&this.url.searchParams.append("end_date",this.endDate),this.url.searchParams.append("status",document.getElementById("status").value),this.url.searchParams.append("show_payments_table",+this.showPaymentsTable),this.url.searchParams.append("show_aging_table",+this.showAgingTable),this.url.href}updatePdf(){document.querySelector("meta[name=pdf-url]").content=this.composedUrl;let e=document.querySelector("#pdf-iframe");e&&(e.src=this.composedUrl),document.querySelector("meta[name=pdf-url]").dispatchEvent(new Event("change"))}handle(){this.bindEventListeners(),document.querySelector("#pdf-download").addEventListener("click",()=>{let e=new URL(this.composedUrl);e.searchParams.append("download",1),window.location.href=e.href})}}new a().handle();
