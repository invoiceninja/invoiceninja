/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */const p=(m,t)=>{let n=document.getElementById(m),l=n.querySelector(`input[value="${t}"]`);if(l)return l.remove();let e=document.createElement("INPUT");e.setAttribute("name","file_hash[]"),e.setAttribute("value",t),e.hidden=!0,n.append(e)};window.appendToElement=p;
