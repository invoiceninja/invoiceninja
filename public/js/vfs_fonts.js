window.pdfMake = window.pdfMake || {}; window.pdfMake.vfs = {};
if(window.ninjaFontVfs)ninjaLoadFontVfs();
function ninjaLoadFontVfs(){
  jQuery.each(window.ninjaFontVfs, function(font, files){
    jQuery.each(files, function(filename, file){
      window.pdfMake.vfs[font+'/'+filename] = file;
    });
  })
}