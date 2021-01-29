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
 * Remove flashing message div after 3 seconds.
 */
document.querySelectorAll('.disposable-alert').forEach((element) => {
    setTimeout(() => {
        element.remove();
    }, 5000);
});
