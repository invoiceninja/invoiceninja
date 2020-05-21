/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

window.CKOConfig = {
    publicKey: get("public-key"),
    customerEmail: get("customer-email"),
    value: get("value"),
    currency: get("currency"),
    paymentMode: "cards",
    cardFormMode: "cardTokenisation",
    cardTokenised: function(event) {
        document.getElementById("payment-form").submit();
    }
};

function get(selector) {
    return document.querySelector(`meta[name="${selector}"]`).content;
}
