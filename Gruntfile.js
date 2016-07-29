module.exports = function(grunt) {

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    dump_dir: (function() {
      var out = {};

      grunt.file.expand({ filter: 'isDirectory'}, 'public/fonts/invoice-fonts/*').forEach(function(path) {
        var fontName = /[^/]*$/.exec(path)[0],
            files = {},
            license='';

        // Add license text
        grunt.file.expand({ filter: 'isFile'}, path+'/*.txt').forEach(function(path) {
            var licenseText = grunt.file.read(path);

            // Fix anything that could escape from the comment
            licenseText = licenseText.replace(/\*\//g,'*\\/');

            license += "/*\n"+licenseText+"\n*/";
        });

        // Create files list
        files['public/js/vfs_fonts/'+fontName+'.js'] = [path+'/*.ttf'];

        out[fontName] = {
          options: {
            pre: license+'window.ninjaFontVfs=window.ninjaFontVfs||{};window.ninjaFontVfs.'+fontName+'=',
            rootPath: path+'/'
          },
          files: files
        };
      });

      // Return the computed object
      return out;
    }()),
    concat: {
      options: {
          process: function(src, filepath) {
              var basepath = filepath.substring(7, filepath.lastIndexOf('/') + 1);
              // Fix relative paths for css files
              if(filepath.indexOf('.css', filepath.length - 4) !== -1) {
                  return src.replace(/(url\s*[\("']+)\s*([^'"\)]+)(['"\)]+;?)/gi,  function(match, start, url, end, offset, string) {
                      if(url.indexOf('data:') === 0) {
                          // Skip data urls
                          return match;

                      } else if(url.indexOf('/') === 0) {
                          // Skip absolute urls
                          return match;

                      } else {
                          return start + basepath + url + end;
                      }
                  });

              // Fix source maps locations
              } else if(filepath.indexOf('.js', filepath.length - 4) !== -1) {
                   return src.replace(/(\/[*\/][#@]\s*sourceMappingURL=)([^\s]+)/gi,  function(match, start, url, offset, string) {
                      if(url.indexOf('/') === 0) {
                          // Skip absolute urls
                          return match;

                      } else {
                          return start + basepath + url;
                      }
                  });

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
          'public/vendor/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.de.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.da.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.pt-BR.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.nl.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.fr.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.it.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.lt.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.no.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.es.min.js',
          'public/vendor/bootstrap-datepicker/dist/locales/bootstrap-datepicker.sv.min.js',
		  'public/vendor/dropzone/dist/min/dropzone.min.js',
          'public/vendor/typeahead.js/dist/typeahead.jquery.min.js',
          'public/vendor/accounting/accounting.min.js',
          'public/vendor/spectrum/spectrum.js',
          'public/vendor/jspdf/dist/jspdf.min.js',
          'public/vendor/moment/min/moment.min.js',
          'public/vendor/moment-timezone/builds/moment-timezone-with-data.min.js',
          'public/vendor/stacktrace-js/dist/stacktrace-with-polyfills.min.js',
          'public/vendor/fuse.js/src/fuse.min.js',
          'public/vendor/sweetalert/dist/sweetalert.min.js',
          //'public/vendor/moment-duration-format/lib/moment-duration-format.js',
          //'public/vendor/pdfmake/build/pdfmake.min.js',
          //'public/vendor/pdfmake/build/vfs_fonts.js',
          //'public/js/vfs_fonts.js',
          'public/js/bootstrap-combobox.js',
          'public/js/script.js',
          'public/js/pdf.pdfmake.js',
        ],
        dest: 'public/built.js',
        nonull: true
      },
      /*js_public: {
        src: [
          'public/js/simpleexpand.js',
          'public/js/valign.js',
          'public/js/bootstrap.min.js',
          'public/js/simpleexpand.js',
          'public/vendor/bootstrap/dist/js/bootstrap.min.js',
          'public/js/bootstrap-combobox.js',

        ],
        dest: 'public/built.public.js',
        nonull: true
      },
      css: {
        src: [
          'public/vendor/bootstrap/dist/css/bootstrap.min.css',
          'public/vendor/datatables/media/css/jquery.dataTables.css',
          'public/vendor/datatables-bootstrap3/BS3/assets/css/datatables.css',
          'public/vendor/font-awesome/css/font-awesome.min.css',
          'public/vendor/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css',
		  'public/vendor/dropzone/dist/min/dropzone.min.css',
          'public/vendor/spectrum/spectrum.css',
          'public/css/bootstrap-combobox.css',
          'public/css/typeahead.js-bootstrap.css',
          'public/vendor/sweetalert/dist/sweetalert.css',
          'public/css/style.css',
        ],
        dest: 'public/css/built.css',
        nonull: true,
        options: {
            process: false
        }
      },*/
      /*css_public: {
        src: [
          'public/vendor/bootstrap/dist/css/bootstrap.min.css',
          'public/vendor/font-awesome/css/font-awesome.min.css',
          'public/css/bootstrap-combobox.css',
          'public/vendor/datatables/media/css/jquery.dataTables.css',
          'public/vendor/datatables-bootstrap3/BS3/assets/css/datatables.css',
          'public/css/public.style.css',
        ],
        dest: 'public/css/built.public.css',
        nonull: true,
        options: {
            process: false
        }
      },*/
      /*js_pdf: {
        src: [
          'public/js/pdf_viewer.js',
          'public/js/compatibility.js',
          'public/js/pdfmake.min.js',
          'public/js/vfs.js',
        ],
        dest: 'public/pdf.built.js',
        nonull: true
      }*/
    }
  });

  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-dump-dir');

  grunt.registerTask('default', ['dump_dir', 'concat']);

};
