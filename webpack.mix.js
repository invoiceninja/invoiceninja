const mix = require("laravel-mix");
const tailwindcss = require("tailwindcss");

mix.js("resources/js/app.js", "public/js")
    .js(
        "resources/js/clients/payment_methods/authorize-authorize-card.js",
        "public/js/clients/payment_methods/authorize-authorize-card.js"
    )
    .js(
        "resources/js/clients/payments/authorize-credit-card-payment.js",
        "public/js/clients/payments/authorize-credit-card-payment.js"
    )
    .js(
        "resources/js/clients/payments/stripe-ach.js",
        "public/js/clients/payments/stripe-ach.js"
    )
    .js(
        "resources/js/clients/invoices/action-selectors.js",
        "public/js/clients/invoices/action-selectors.js"
    )
    .js(
        "resources/js/clients/invoices/payment.js",
        "public/js/clients/invoices/payment.js"
    )
    .js(
        "resources/js/clients/payments/stripe-sofort.js",
        "public/js/clients/payments/stripe-sofort.js"
    )
    .js(
        "resources/js/clients/payments/stripe-alipay.js",
        "public/js/clients/payments/stripe-alipay.js"
    )
    .js(
        "resources/js/clients/payments/checkout-credit-card.js",
        "public/js/clients/payments/checkout-credit-card.js"
    )
    .js(
        "resources/js/clients/quotes/action-selectors.js",
        "public/js/clients/quotes/action-selectors.js"
    )
    .js(
        "resources/js/clients/quotes/approve.js",
        "public/js/clients/quotes/approve.js"
    )
    .js(
        "resources/js/clients/payments/stripe-credit-card.js",
        "public/js/clients/payments/stripe-credit-card.js"
    )
    .js(
        "resources/js/setup/setup.js",
        "public/js/setup/setup.js"
    )
    .js(
        "node_modules/card-js/card-js.min.js",
        "public/js/clients/payments/card-js.min.js"
    )
    .js(
        "resources/js/clients/shared/pdf.js",
        "public/js/clients/shared/pdf.js"
    )
    .js(
        "resources/js/clients/shared/multiple-downloads.js",
        "public/js/clients/shared/multiple-downloads.js"
    )
    .js(
        "resources/js/clients/linkify-urls.js",
        "public/js/clients/linkify-urls.js"
    )
    .js(
        "resources/js/clients/payments/braintree-credit-card.js",
        "public/js/clients/payments/braintree-credit-card.js"
    );

mix.copyDirectory('node_modules/card-js/card-js.min.css', 'public/css/card-js.min.css');

mix.sass("resources/sass/app.scss", "public/css")
    .options({
        processCssUrls: false,
        postCss: [tailwindcss("./tailwind.config.js")]
    });
mix.version();
mix.disableNotifications();
