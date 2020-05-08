const defaultTheme = require("tailwindcss/defaultTheme");

module.exports = {
    purge: [
        './resources/views/portal/ninja2020/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ["Open Sans", ...defaultTheme.fontFamily.sans]
            }
        }
    },
    variants: {},
    plugins: [require("@tailwindcss/ui")]
};
