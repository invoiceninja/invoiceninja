/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

import linkifyUrls from 'linkify-urls';

document
    .querySelectorAll('[data-ref=entity-terms]')
    .forEach((text) => {

        if (linkifyUrls === 'function') {

            text.innerHTML = linkifyUrls(text.innerText, {
                attributes: {target: '_blank', class: 'text-primary'}
            });

        }

    });
