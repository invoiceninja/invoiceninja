const mix = require("laravel-mix");
const tailwindcss = require("tailwindcss");

require("laravel-mix-purgecss");

mix.js("resources/js/app.js", "public/js")
    .js(
        "resources/js/clients/payment_methods/authorize-stripe-card.js",
        "public/js/clients/payment_methods/authorize-stripe-card.js"
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
        "resources/js/clients/quotes/action-selectors.js",
        "public/js/clients/quotes/action-selectors.js"
    )
    .js(
        "resources/js/clients/quotes/approve.js",
        "public/js/clients/quotes/approve.js"
    )
    .js(
        "resources/js/clients/payments/process.js",
        "public/js/clients/payments/process.js"
    )
    .js(
        "resources/js/setup/setup.js", 
        "public/js/setup/setup.js"
    )
    .js(
        "resources/js/clients/shared/pdf.js",
        "public/js/clients/shared/pdf.js"
    );

mix.sass("resources/sass/app.scss", "public/css")
    .options({
        processCssUrls: false,
        postCss: [tailwindcss("./tailwind.config.js")]
    })
    .purgeCss({
        enabled: mix.inProduction(),
        extensions: ["html", "php"]
    });

mix.version();
mix.disableNotifications();
