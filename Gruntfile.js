module.exports = function(grunt) {

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    concat: {
      js: {
        src: [
          'public/vendor/jquery/dist/jquery.js',
          'public/vendor/jquery-ui/ui/minified/jquery-ui.min.js', 
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
          //'public/js/jspdf.source.js',
          //'public/js/jspdf.plugin.split_text_to_size.js',
          'public/js/script.js',
        ],
        dest: 'public/built.js'
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
          'public/css/style.css',
        ],        
        dest: 'public/built.css'
      },
      css_public: {
        src: [
          'public/vendor/bootstrap/dist/css/bootstrap.min.css',
          'public/css/bootstrap.splash.css',
          'public/css/splash.css',
        ],
        dest: 'public/built.public.css'
      }
    }
  });  

  grunt.loadNpmTasks('grunt-contrib-concat');

  grunt.registerTask('default', ['concat']);

};