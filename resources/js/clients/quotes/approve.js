/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

import { setupApproval } from '../approval';

const enabled = name => Boolean(+document.querySelector(`meta[name="${name}"]`)?.content);

setupApproval({
    signature: enabled('require-quote-signature'),
    terms: enabled('show-quote-terms'),
    input: enabled('accept-user-input'),
    docuninja: enabled('docuninja-active'),
});
