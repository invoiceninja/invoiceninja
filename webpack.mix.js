const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.webpackConfig({
        resolve: {
            extensions: ['.ts']
        },
        module: {
            rules: [
                {
                    test: /\.ts$/,
                    loader: 'ts-loader'
                }
            ]
        }
    });

mix.js('resources/js/src/client/client_edit.ts', 'public/js');
mix.js('resources/js/app.js', 'public/js/vendor');
mix.js('node_modules/@coreui/coreui/dist/js/coreui.js', 'public/js');
mix.scripts([
    'public/js/vendor/app.js'
], 'public/js/ninja.js');

mix.minify('public/js/ninja.js');
mix.minify('public/js/coreui.js');
mix.minify('public/js/client_edit.js');

mix.styles([
    'node_modules/@coreui/coreui/dist/css/coreui.css',
    'node_modules/@coreui/icons/css/coreui-icons.css',
    'node_modules/font-awesome/css/font-awesome.css'
], 'public/css/ninja.css');

mix.minify('public/css/ninja.css');

mix.copyDirectory('node_modules/font-awesome/fonts', 'public/fonts');

mix.version();
