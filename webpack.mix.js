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
    'public/js/vendor/app.js',
], 'public/js/ninja.js');


mix.sass('resources/sass/app.scss', 'public/css/vendor');

mix.styles([
    'public/css/vendor/app.css',
    'public/css/vendor/these.css',
    'public/css/vendor/animate.css',
    'public/css/vendor/bootstrap-select.css',
    'public/css/vendor/cs-skin-elastic.css',
    'public/css/vendor/flag-icon.min.css'
], 'public/css/ninja.css');

mix.version();