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


    /**
     * JS configuration
     */
    mix.scripts(['resources/assets/js/Chart.js'], 'public/js/Chart.min.js')
        .scripts(['resources/assets/js/d3.js'], 'public/js/d3.min.js');


});
