const mix = require("laravel-mix");
const tailwindcss = require("tailwindcss");

mix.js("resources/js/app.js", "public/js")
    .js("resources/js/clients/payment_methods/authorize-stripe-card.js", "public/js/clients/payment_methods/authorize-stripe-card.js")
    .js("resources/js/clients/invoices/action-selectors.js", "public/js/clients/invoices/action-selectors.js")
    .js("resources/js/clients/invoices/payment.js", "public/js/clients/invoices/payment.js");

mix.sass("resources/sass/app.scss", "public/css")
    .options({
        processCssUrls: false,
        postCss: [tailwindcss("./tailwind.config.js")]
    });

mix.version();
mix.disableSuccessNotifications();
