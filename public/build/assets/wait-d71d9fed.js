/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */function i(...e){return new Promise(t=>{if(!e.length){t([]);return}const r=e.map(n=>document.querySelector(n)).filter(Boolean);if(r.length===e.length){t(r);return}const o=new MutationObserver(()=>{const n=e.map(u=>document.querySelector(u)).filter(Boolean);n.length===e.length&&(o.disconnect(),t(n))});o.observe(document.body,{childList:!0,subtree:!0})})}export{i as w};
