var elixir = require('laravel-elixir');

/**
 * Configure Elixir to explicitly generate source maps
 *
 * @type {boolean}
 */
elixir.config.sourcemaps = true;

/**
 * Directory for JS assets that are not handled by Bower
 *
 * @type {string}
 */
var resourcesJsDirectory = './resources/assets/js/';

/**
 * Directory for CSS assets that are not handled by Bower
 *
 * @type {string}
 */
var resourcesCssDirectory = './resources/assets/css/';

/**
 * Directory for JS source files that are handled by Bower.
 * Bower source file directory.
 *
 * @type {string}
 */
var bowerDirectoy = './resources/assets/bower/';

elixir(function(mix) {

    // Built built.css
    mix.styles([
        bowerDirectoy + 'bootstrap/dist/css/bootstrap.css',
        bowerDirectoy + 'bootstrap-combobox/css/bootstrap-combobox.css',
        bowerDirectoy + 'bootstrap-datepicker/dist/css/bootstrap-datepicker3.css',
        bowerDirectoy + 'datatables/media/css/jquery.dataTables.css',
        bowerDirectoy + 'datatables-bootstrap3/BS3/assets/css/datatables.css',
        bowerDirectoy + 'font-awesome/css/font-awesome.css',
        bowerDirectoy + 'dropzone/dist/dropzone.css',
        bowerDirectoy + 'spectrum/spectrum.css',
        resourcesCssDirectory + 'typeahead.js-bootstrap.css',
        resourcesCssDirectory + 'style.css'
    ], 'public/css/built.min.css');

    // Built built.public.css
    mix.styles([
        bowerDirectoy + 'bootstrap/dist/css/bootstrap.css',
        bowerDirectoy + 'bootstrap-combobox/css/bootstrap-combobox.css',
        bowerDirectoy + 'font-awesome/css/font-awesome.css',
        bowerDirectoy + 'datatables/media/css/jquery.dataTables.css',
        bowerDirectoy + 'datatables-bootstrap3/BS3/assets/css/datatables.css',
        resourcesCssDirectory + 'public.style.css'
    ], 'public/css/built.public.min.css');

    mix.copy(resourcesCssDirectory + 'themes', 'public/css/themes');

    // Copy other CSS files to public
    mix.styles(bowerDirectoy + 'quill/dist/quill.snow.css', 'public/css/quill.snow.min.css')
        .styles(bowerDirectoy + 'lightbox2/dist/css/lightbox.css', 'public/css/lightbox.min.css')
        .styles(bowerDirectoy + 'datetimepicker/jquery.datetimepicker.css', 'public/css/jquery.datetimepicker.min.css')
        .styles(bowerDirectoy + 'jsoneditor/dist/jsoneditor.css', 'public/css/jsoneditor.min.css')
        .styles(bowerDirectoy + 'bootstrap/dist/css/bootstrap.css', 'public/css/bootstrap.min.css')
        .styles(resourcesCssDirectory + 'app.css', 'public/css/app.min.css')
        .styles(resourcesCssDirectory + 'customCss.css', 'public/css/customCss.min.css')
        .styles(resourcesCssDirectory + 'style.css', 'public/css/style.min.css')
        .styles(resourcesCssDirectory + 'public.style.css', 'public/public.style.min.css');


    // Built built.js
    mix.scripts([
        bowerDirectoy + 'jquery/dist/jquery.js',
        bowerDirectoy + 'jquery-ui/jquery-ui.js',
        bowerDirectoy + 'bootstrap/dist/js/bootstrap.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/js/bootstrap-datepicker.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.de.min.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.da.min.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.pt-BR.min.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.nl.min.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.fr.min.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.it.min.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.lt.min.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.no.min.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.es.min.js',
        bowerDirectoy + 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.sv.min.js',
        bowerDirectoy + 'datatables/media/js/jquery.dataTables.js',
        bowerDirectoy + 'datatables-bootstrap3/BS3/assets/js/datatables.js',
        bowerDirectoy + 'knockout.js/knockout.js',
        bowerDirectoy + 'knockout-mapping/build/output/knockout.mapping-latest.js',
        bowerDirectoy + 'knockout-sortable/build/knockout-sortable.min.js',
        bowerDirectoy + 'underscore/underscore.js',
        bowerDirectoy + 'dropzone/dist/dropzone.js',
        bowerDirectoy + 'typeahead.js/dist/typeahead.jquery.js',
        bowerDirectoy + 'accounting/accounting.js',
        bowerDirectoy + 'spectrum/spectrum.js',
        bowerDirectoy + 'jspdf/dist/jspdf.min.js',
        bowerDirectoy + 'jsPDF-plugins/plugins/split_text_to_size.js',
        bowerDirectoy + 'moment/moment.js',
        bowerDirectoy + 'moment-timezone/builds/moment-timezone-with-data.js',
        bowerDirectoy + 'stacktrace-js/dist/stacktrace-with-promises-and-json-polyfills.min.js',
        bowerDirectoy + 'fuse.js/src/fuse.js',
        bowerDirectoy + 'bootstrap-combobox/js/bootstrap-combobox.js',
        resourcesJsDirectory + 'script.js',
        resourcesJsDirectory + 'pdfmake-ninja.js'
    ], 'public/built.js');

    // Built built.public.js
    mix.scripts([
        bowerDirectoy + 'bootstrap/dist/js/bootstrap.js',
        bowerDirectoy + 'bootstrap-combobox/js/bootstrap-combobox.js'
    ], 'public/built.public.js');

    // Built pdf.built.js
    mix.scripts([
        resourcesJsDirectory + 'compatibility.js',
        resourcesJsDirectory + 'pdf_viewer.js',
        bowerDirectoy + 'pdfmake/build/pdfmake.js',
        resourcesJsDirectory + 'vfs.js'
    ], 'public/pdf.built.js');

    // Copy VFS Fonts to public directory
    mix.copy(resourcesJsDirectory + 'vfs_fonts', 'public/js/vfs_fonts');

    // Copy SVG file for Jsoneditor to public directory
    mix.copy(bowerDirectoy + 'jsoneditor/dist/img', 'public/css/img');

    // Copy other JS files to public
    mix.scripts(resourcesJsDirectory + 'Chart.js', 'public/js/Chart.min.js')
        .scripts(bowerDirectoy + 'datetimepicker/build/jquery.datetimepicker.full.js', 'public/js/jquery.datetimepicker.min.js')
        .scripts(bowerDirectoy + 'd3/d3.js', 'public/js/d3.min.js')
        .scripts(bowerDirectoy + 'quill/dist/quill.js', 'public/js/quill.min.js')
        .scripts(bowerDirectoy + 'lightbox2/dist/js/lightbox.js', 'public/js/lightbox.min.js')
        .scripts(bowerDirectoy + 'bootstrap-combobox/js/bootstrap-combobox.js', 'public/js/bootstrap-combobox.min.js')
        .scripts(bowerDirectoy + 'jsoneditor/dist/jsoneditor.js', 'public/js/jsoneditor.min.js')
        .scripts(resourcesJsDirectory + 'pdf.js', 'public/js/pdf.min.js')
        .scripts(resourcesJsDirectory + 'pdf_viewer.worker.js', 'public/js/pdf_viewer.worker.js')
        .scripts(resourcesJsDirectory + 'script.js', 'public/js/script.js');
});
