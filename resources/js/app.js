/**
 * Axios
 *
 * Promise based HTTP client for the browser and node.js
 * https://github.com/axios/axios
 */
window.axios = require('axios');

/**
 * card-validator
 *
 * Validate credit cards as users type.
 * https://github.com/braintree/card-validator
 */
window.valid = require('card-validator');

/**
 * Toggle processing overlay.
 */
window.processingOverlay = (show) => {
    if (show) {
        return document
            .getElementById('processing-overlay')
            .classList.remove('hidden');
    }

    return document
        .getElementById('processing-overlay')
        .classList.add('hidden');
};
