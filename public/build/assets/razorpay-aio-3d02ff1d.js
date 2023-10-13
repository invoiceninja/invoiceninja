/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */var a;let n=JSON.parse((a=document.querySelector("meta[name=razorpay-options]"))==null?void 0:a.content);n.handler=function(e){document.getElementById("razorpay_payment_id").value=e.razorpay_payment_id,document.getElementById("razorpay_signature").value=e.razorpay_signature,document.getElementById("server-response").submit()};n.modal={ondismiss:function(){t.disabled=!1}};let o=new Razorpay(n),t=document.getElementById("pay-now");t.onclick=function(e){t.disabled=!0,o.open()};
