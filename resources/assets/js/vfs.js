window.pdfMake = window.pdfMake || {}; window.pdfMake.vfs = {};
if(window.ninjaFontVfs)ninjaLoadFontVfs();
function ninjaLoadFontVfs(){
  jQuery.each(window.ninjaFontVfs, function(font, files){
    jQuery.each(files, function(filename, file){
      window.pdfMake.vfs['fonts/'+font+'/'+filename] = file;
    });
  })
}
function ninjaAddVFSDoc(name,content){
  window.pdfMake.vfs['docs/'+name] = content;
  if(window.refreshPDF)refreshPDF(true);
  jQuery(document).trigger('ninjaVFSDocAdded');
}