module.exports = function(grunt) {

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    concat: {
      options: {
          process: function(src, filepath) {
              // Fix path for image and font resources
              if(filepath.indexOf('.css', filepath.length - 4) !== -1) {
                return src.replace(/\.\.\/(images|fonts)\//g, '$1/');
              // Don't do anything for unknown file types
              } else {
                return src;
              }
          },
      },
      js: {
        src: [
          'public/vendor/jquery/dist/jquery.js',
          'public/vendor/jquery-ui/jquery-ui.min.js',
          'public/vendor/bootstrap/dist/js/bootstrap.min.js',
          'public/vendor/datatables/media/js/jquery.dataTables.js',
          'public/vendor/datatables-bootstrap3/BS3/assets/js/datatables.js',
          'public/vendor/knockout.js/knockout.js',
          'public/vendor/knockout-mapping/build/output/knockout.mapping-latest.js',
          'public/vendor/knockout-sortable/build/knockout-sortable.min.js',
          'public/vendor/underscore/underscore.js',
          'public/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js',
          'public/vendor/typeahead.js/dist/typeahead.min.js',
          'public/vendor/accounting/accounting.min.js',
          'public/vendor/spectrum/spectrum.js',
          'public/js/bootstrap-combobox.js',
          'public/vendor/jspdf/dist/jspdf.min.js',
          'public/vendor/handsontable/dist/jquery.handsontable.full.min.js',
          //'public/js/jspdf.source.js',
          //'public/js/jspdf.plugin.split_text_to_size.js',
          'public/js/script.js',
        ],
        dest: 'public/built.js',
        nonull: true
      },
      css: {
        src: [
          'public/vendor/bootstrap/dist/css/bootstrap.min.css',
          'public/vendor/datatables/media/css/jquery.dataTables.css',
          'public/vendor/datatables-bootstrap3/BS3/assets/css/datatables.css',
          'public/vendor/font-awesome/css/font-awesome.min.css',
          'public/vendor/bootstrap-datepicker/css/datepicker.css',
          'public/vendor/spectrum/spectrum.css',
          'public/css/bootstrap-combobox.css',
          'public/css/typeahead.js-bootstrap.css',
          'public/vendor/handsontable/dist/jquery.handsontable.full.css',
          'public/css/style.css',
        ],
        dest: 'public/built.css',
        nonull: true
      },
      css_public: {
        src: [
          'public/vendor/bootstrap/dist/css/bootstrap.min.css',
          'public/css/bootstrap.splash.css',
          'public/css/splash.css',
        ],
        dest: 'public/built.public.css',
        nonull: true
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-concat');

  grunt.registerTask('default', ['concat']);

};
