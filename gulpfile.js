var elixir = require('laravel-elixir');

/**
 * Set Elixir Source Maps
 *
 * @type {boolean}
 */
elixir.config.sourcemaps = true;

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
var bowerDir = '../bower';

elixir(function(mix) {

    /**
     * CSS configuration
     */
    mix.styles([
        bowerDir + '/bootstrap/dist/css/bootstrap.css',
        bowerDir + '/font-awesome/css/font-awesome.css',
        bowerDir + '/datatables/media/css/jquery.dataTables.css',
        bowerDir + '/datatables-bootstrap3/BS3/assets/css/datatables.css',
        'bootstrap-combobox.css',
        'public.style.css',
        'fonts.css'
    ], 'public/css/built.public.css');

    mix.styles([
        bowerDir + '/bootstrap/dist/css/bootstrap.css',
        bowerDir + '/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css',
        bowerDir + '/datatables/media/css/jquery.dataTables.css',
        bowerDir + '/datatables-bootstrap3/BS3/assets/css/datatables.css',
        bowerDir + '/font-awesome/css/font-awesome.css',
        bowerDir + '/dropzone/dist/dropzone.css',
        bowerDir + '/spectrum/spectrum.css',
        bowerDir + '/sweetalert2/dist/sweetalert2.css',
        'bootstrap-combobox.css',
        'typeahead.js-bootstrap.css',
        'style.css',
        'sidebar.css',
        'colors.css',
        'fonts.css',
    ], 'public/css/built.css');

    mix.styles([
       'login.css'
    ], 'public/css/built.login.css');

    mix.styles([
        bowerDir + '/bootstrap-daterangepicker/daterangepicker.css'
    ], 'public/css/daterangepicker.css');

    mix.styles([
        bowerDir + '/select2/dist/css/select2.css'
    ], 'public/css/select2.css');

    mix.styles([
        bowerDir + '/tablesorter/dist/css/theme.bootstrap_3.min.css',
        bowerDir + '/tablesorter/dist/css/theme.bootstrap.min.css',
        bowerDir + '/tablesorter/dist/css/widget.grouping.min.css'
    ], 'public/css/tablesorter.css');


    /**
     * JS configuration
     */
    mix.scripts(['resources/assets/js/Chart.js'], 'public/js/Chart.min.js')
        .scripts(['resources/assets/js/d3.js'], 'public/js/d3.min.js');

    mix.scripts([
        'pdf_viewer.js',
        'compatibility.js',
        //bowerDir + '/pdfmake/build/pdfmake.js',
        'pdfmake.js',
        'vfs.js'
    ], 'public/pdf.built.js');

    mix.scripts([
        bowerDir + '/bootstrap-daterangepicker/daterangepicker.js'
    ], 'public/js/daterangepicker.min.js');

    mix.scripts([
        bowerDir + '/card/dist/card.js',
    ], 'public/js/card.min.js');

    mix.scripts([
        bowerDir + '/tablesorter/dist/js/jquery.tablesorter.combined.js',
        bowerDir + '/tablesorter/dist/js/widgets/widget-grouping.min.js',
        bowerDir + '/tablesorter/dist/js/widgets/widget-uitheme.min.js',
        bowerDir + '/tablesorter/dist/js/widgets/widget-filter.min.js',
    ], 'public/js/tablesorter.min.js');

    mix.scripts([
        bowerDir + '/select2/dist/js/select2.js',
        'resources/assets/js/maximize-select2-height.js',
    ], 'public/js/select2.min.js');

    mix.scripts([
        bowerDir + '/jSignature/libs/jSignature.min.js'
    ], 'public/js/jSignature.min.js');

    mix.scripts([
        bowerDir + '/jquery/dist/jquery.js',
        bowerDir + '/jquery-ui/jquery-ui.js',
        bowerDir + '/bootstrap/dist/js/bootstrap.js',
        bowerDir + '/datatables/media/js/jquery.dataTables.js',
        bowerDir + '/datatables-bootstrap3/BS3/assets/js/datatables.js',
        bowerDir + '/knockout.js/knockout.js',
        bowerDir + '/knockout-mapping/build/output/knockout.mapping-latest.js',
        bowerDir + '/knockout-sortable/build/knockout-sortable.js',
        bowerDir + '/underscore/underscore.js',
        bowerDir + '/bootstrap-datepicker/dist/js/bootstrap-datepicker.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.de.min.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.da.min.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.pt-BR.min.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.nl.min.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.fr.min.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.it.min.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.lt.min.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.no.min.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.es.min.js',
        bowerDir + '/bootstrap-datepicker/dist/locales/bootstrap-datepicker.sv.min.js',
        bowerDir + '/dropzone/dist/dropzone.js',
        bowerDir + '/typeahead.js/dist/typeahead.jquery.js',
        bowerDir + '/accounting/accounting.js',
        bowerDir + '/spectrum/spectrum.js',
        bowerDir + '/moment/moment.js',
        bowerDir + '/moment-timezone/builds/moment-timezone-with-data.js',
        //bowerDir + '/stacktrace-js/dist/stacktrace-with-polyfills.min.js',
        bowerDir + '/es6-promise/es6-promise.auto.js',
        bowerDir + '/sweetalert2/dist/sweetalert2.js',
        //bowerDir + '/sweetalert/dist/sweetalert-dev.js',
        bowerDir + '/nouislider/distribute/nouislider.js',
        bowerDir + '/mousetrap/mousetrap.js',
        bowerDir + '/fuse.js/src/fuse.js',
        'bootstrap-combobox.js',
        'script.js',
        'pdf.pdfmake.js',
    ], 'public/built.js');


});
