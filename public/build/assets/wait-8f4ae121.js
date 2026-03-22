/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */function i(...e){return new Promise(n=>{if(!e.length){n([]);return}const r=e.map(t=>document.querySelector(t)).filter(Boolean);if(r.length===e.length){n(r);return}const o=new MutationObserver(()=>{const t=e.map(u=>document.querySelector(u)).filter(Boolean);t.length===e.length&&(o.disconnect(),n(t))});o.observe(document.body,{childList:!0,subtree:!0})})}function a(){const e=document.querySelector('meta[name="instant-payment"]');return!!(e&&e instanceof HTMLMetaElement&&e.content==="yes")}export{a as i,i as w};
