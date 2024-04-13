import{A as a}from"./index-08e160a7.js";import"./_commonjsHelpers-725317a4.js";/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class c{constructor(){this.checkDbButton=document.getElementById("test-db-connection"),this.checkDbAlert=document.getElementById("database-response")}handleDatabaseCheck(){let e=document.querySelector("meta[name=setup-db-check]").content,t={};document.querySelector('input[name="db_host"]')&&(t={db_host:document.querySelector('input[name="db_host"]').value,db_port:document.querySelector('input[name="db_port"]').value,db_database:document.querySelector('input[name="db_database"]').value,db_username:document.querySelector('input[name="db_username"]').value,db_password:document.querySelector('input[name="db_password"]').value}),this.checkDbButton.disabled=!0,a.post(e,t).then(s=>{this.handleSuccess(this.checkDbAlert,"account-wrapper"),this.handleSuccess(this.checkDbAlert,"submit-wrapper")}).catch(s=>this.handleFailure(this.checkDbAlert,s.response.data.message)).finally(()=>this.checkDbButton.disabled=!1)}handleSuccess(e,t=null){e.classList.remove("alert-failure"),e.innerText="Success!",e.classList.add("alert-success"),t&&(document.getElementById(t).classList.remove("hidden"),document.getElementById(t).scrollIntoView({behavior:"smooth",block:"center"}))}handleFailure(e,t=null){e.classList.remove("alert-success"),e.innerText=t||"Oops, looks like something isn't correct!",e.classList.add("alert-failure")}handle(){this.checkDbButton.addEventListener("click",()=>this.handleDatabaseCheck())}}new c().handle();
