var elixir = require('laravel-elixir');

/**
 * Set Elixir Source Maps
 *
 * @type {boolean}
 */
elixir.config.sourcemaps = true;

/**
 * Configuring assets path.
 * Explicitly setting it to empty, as we're not using Laravels resources/assets folder
 *
 * @type {string}
 */
elixir.config.assetsPath = '';

/**
 * Configuring Javascript assets path.
 * Explicitly setting it to empty, as we're not using Laravels resources/assets/js folder
 *
 * @type {string}
 */
elixir.config.js.folder = '';

/**
 * Configuring CSS assets path.
 * Explicitly setting it to empty, as we're not using Laravels resources/assets/css folder
 *
 * @type {string}
 */
elixir.config.css.folder = '';

/**
 * Remove all CSS comments
 *
 * @type {{discardComments: {removeAll: boolean}}}
 */
elixir.config.css.minifier.pluginOptions = {
    discardComments: {
        removeAll: true
    }
};

/**
 * Directory for bower source files.
 * If changing this, please also see .bowerrc
 *
 * @type {string}
 */
var bowerDir = 'public/vendor';

elixir(function(mix) {

    /**
     * CSS configuration
     */
    mix.styles([
        bowerDir + '/bootstrap/dist/css/bootstrap.css',
        bowerDir + '/font-awesome/css/font-awesome.css',
        bowerDir + '/datatables/media/css/jquery.dataTables.css',
        bowerDir + '/datatables-bootstrap3/BS3/assets/css/datatables.css',
        'public/css/bootstrap-combobox.css',
        'public/css/public.style.css'
    ], 'public/css/built.public.css');

    mix.styles([
        bowerDir + '/bootstrap/dist/css/bootstrap.css',
        bowerDir + '/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css',
        bowerDir + '/datatables/media/css/jquery.dataTables.css',
        bowerDir + '/datatables-bootstrap3/BS3/assets/css/datatables.css',
        bowerDir + '/font-awesome/css/font-awesome.css',
        bowerDir + '/dropzone/dist/dropzone.css',
        bowerDir + '/spectrum/spectrum.css',
        bowerDir + '/sweetalert/dist/sweetalert.css',
        'public/css/bootstrap-combobox.css',
        'public/css/typeahead.js-bootstrap.css',
        'public/css/style.css'
    ], 'public/css/built.css');

    /**
     * JS configuration
     */
    mix.scripts(['resources/assets/js/Chart.js'], 'public/js/Chart.min.js')
        .scripts(['resources/assets/js/d3.js'], 'public/js/d3.min.js');

    mix.scripts([
        'public/js/pdf_viewer.js',
        'public/js/compatibility.js',
        //'public/js/pdfmake.min.js',
        'public/js/pdfmake.js',
        'public/js/vfs.js'
    ], 'public/pdf.built.js');


});
