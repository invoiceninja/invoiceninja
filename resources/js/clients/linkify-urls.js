/**
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
