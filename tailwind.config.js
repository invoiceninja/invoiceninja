const defaultTheme = require("tailwindcss/defaultTheme");

module.exports = {
    purge: [
        './resources/views/portal/ninja2020/**/*.blade.php',
        './resources/views/email/template/**/*.blade.php',
        './resources/views/email/components/**/*.blade.php',
        './resources/views/themes/ninja2020/**/*.blade.php',
        './resources/views/auth/**/*.blade.php',
        './resources/views/setup/**/*.blade.php'
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ["Open Sans", ...defaultTheme.fontFamily.sans]
            }
        }
    },
    variants: {},
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ]

};
