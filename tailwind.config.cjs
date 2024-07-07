const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
    content: [
        './resources/views/portal/ninja2020/**/*.blade.php',
        './resources/views/email/template/**/*.blade.php',
        './resources/views/email/components/**/*.blade.php',
        './resources/views/themes/ninja2020/**/*.blade.php',
        './resources/views/auth/**/*.blade.php',
        './resources/views/setup/**/*.blade.php',
        './resources/views/billing-portal/**/*.blade.php'
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Open Sans', ...defaultTheme.fontFamily.sans],
            },
            transitionProperty: {
                'height': 'height'
            }
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
};
