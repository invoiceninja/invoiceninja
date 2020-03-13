const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
  theme: {
    extend: {
        fontFamily: {
            sans: ['Open Sans', ...defaultTheme.fontFamily.sans],
        },
    },
  },
  variants: {},
  plugins: [
      require('@tailwindcss/ui'),
  ],
};
