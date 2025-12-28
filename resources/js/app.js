/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import axios from 'axios';
import cardValidator from 'card-validator';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

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
