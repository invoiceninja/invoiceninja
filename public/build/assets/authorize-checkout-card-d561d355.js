/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class t{constructor(){this.button=document.getElementById("pay-button")}init(){this.frames=Frames.init(document.querySelector("meta[name=public-key]").content)}handle(){this.init(),Frames.addEventHandler(Frames.Events.CARD_VALIDATION_CHANGED,e=>{this.button.disabled=!Frames.isCardValid()}),Frames.addEventHandler(Frames.Events.CARD_TOKENIZED,e=>{document.querySelector('input[name="gateway_response"]').value=JSON.stringify(e),document.getElementById("server_response").submit()}),document.querySelector("#authorization-form").addEventListener("submit",e=>{this.button.disabled=!0,e.preventDefault(),Frames.submitCard()})}}new t().handle();
