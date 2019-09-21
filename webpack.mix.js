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


mix.copyDirectory('node_modules/@coreui/coreui/dist/css/coreui.min.css', 'public/vendors/css/coreui.min.css');
mix.copyDirectory('node_modules/@coreui/icons/css/coreui-icons.min.css', 'public/vendors/css/coreui-icons.min.css');
mix.copyDirectory('node_modules/@coreui/coreui/dist/css/bootstrap.min.css', 'public/vendors/css/bootstrap.min.css');
mix.copyDirectory('node_modules/font-awesome/css/font-awesome.min.css', 'public/vendors/css/font-awesome.min.css');
mix.copyDirectory('node_modules/@coreui/coreui/dist/js/coreui.min.js', 'public/vendors/js/coreui.min.js');
mix.copyDirectory('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', 'public/vendors/js/bootstrap.bundle.min.js');
mix.copyDirectory('node_modules/jquery/dist/jquery.min.js', 'public/vendors/js/jquery.min.js');
mix.copyDirectory('node_modules/perfect-scrollbar/dist/perfect-scrollbar.min.js', 'public/vendors/js/perfect-scrollbar.min.js');
mix.copyDirectory('node_modules/jsignature/libs/jSignature.min.js', 'public/vendors/js/jSignature.min.js');
mix.copyDirectory('node_modules/jsignature/libs/flashcanvas.min.js', 'public/vendors/js/flashcanvas.min.js');
mix.copyDirectory('node_modules/jsignature/libs/flashcanvas.swf', 'public/vendors/js/flashcanvas.swf');


mix.copyDirectory('node_modules/select2/dist/css/select2.min.css', 'public/vendors/css/select2.min.css');
mix.copyDirectory('node_modules/select2/dist/js/select2.full.min.js', 'public/vendors/js/select2.min.js');

mix.copyDirectory('node_modules/@ttskch/select2-bootstrap4-theme/dist/select2-bootstrap4.min.css', 'public/vendors/css/select2-bootstrap4.css');

mix.copyDirectory('node_modules/dropzone/dist/min/dropzone.min.css', 'public/vendors/css/dropzone.min.css');
mix.copyDirectory('node_modules/dropzone/dist/min/basic.min.css', 'public/vendors/css/dropzone-basic.min.css');
mix.copyDirectory('node_modules/dropzone/dist/min/dropzone.min.js', 'public/vendors/js/dropzone.min.js');

mix.copyDirectory('node_modules/bootstrap-sweetalert/dist/sweetalert.css', 'public/vendors/css/sweetalert.css');
mix.copyDirectory('node_modules/bootstrap-sweetalert/dist/sweetalert.min.js', 'public/vendors/js/sweetalert.min.js');

mix.copyDirectory('node_modules/font-awesome/fonts', 'public/vendors/fonts');

mix.version();
