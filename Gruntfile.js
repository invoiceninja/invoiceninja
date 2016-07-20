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
    }
  });

  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-dump-dir');

  grunt.registerTask('default', ['dump_dir', 'concat']);

};
