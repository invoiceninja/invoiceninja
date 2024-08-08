import{i as e,w as n}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */function t(){console.log("boot",document.querySelector("meta[name=client-token]"))}e()?t():n("#braintree-credit-card-payment","meta[name=client-token]").then(()=>t());
