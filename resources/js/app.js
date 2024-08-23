/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import axios from 'axios';
import cardValidator from 'card-validator';
import { Livewire, Alpine } from './livewire_temp.esm';

Livewire.start()
window.axios = axios;
window.valid = cardValidator;

/**
 * Remove flashing message div after 3 seconds.
 */
document.querySelectorAll('.disposable-alert').forEach((element) => {
    setTimeout(() => {
        element.remove();
    }, 5000);
});
