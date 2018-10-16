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

mix.js('resources/js/app.js', 'public/js/vendor');

mix.scripts([
    'node_modules/@coreui/coreui/dist/js/coreui.js',
    'node_modules/bootstrap/dist/js/bootstrap.js',
    'public/js/vendor/app.js'
], 'public/js/ninja.js');

mix.styles([
    'node_modules/@coreui/coreui/dist/css/coreui.css'
], 'public/css/ninja.css');

mix.version();
