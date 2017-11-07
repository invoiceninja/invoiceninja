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
    }())
  });

  grunt.loadNpmTasks('grunt-dump-dir');
  grunt.registerTask('default', ['dump_dir']);

};
