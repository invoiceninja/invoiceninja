<iframe id="theFrame" style="display:none" frameborder="1" width="100%" height="{{ isset($pdfHeight) ? $pdfHeight : 1180 }}px"></iframe>
<canvas id="theCanvas" style="display:none;width:100%;border:solid 1px #CCCCCC;"></canvas>

<script type="text/javascript">
  window.logoImages = {};
  
  logoImages.imageLogo1 = "{{ HTML::image_data('images/report_logo1.jpg') }}";
  logoImages.imageLogoWidth1 =120;
  logoImages.imageLogoHeight1 = 40

  logoImages.imageLogo2 = "{{ HTML::image_data('images/report_logo2.jpg') }}";
  logoImages.imageLogoWidth2 =325/2;
  logoImages.imageLogoHeight2 = 81/2;

  logoImages.imageLogo3 = "{{ HTML::image_data('images/report_logo3.jpg') }}";
  logoImages.imageLogoWidth3 =325/2;
  logoImages.imageLogoHeight3 = 81/2;

  @if (file_exists($account->getLogoPath()))
    invoice.image = "{{ HTML::image_data($account->getLogoPath()) }}";
    invoice.imageWidth = {{ $invoice->client->account->getLogoWidth() }};
    invoice.imageHeight = {{ $invoice->client->account->getLogoHeight() }};
  @endif  

  var NINJA = NINJA || {};
  NINJA.primaryColor = "{{ $account->primary_color }}";
  NINJA.secondaryColor = "{{ $account->secondary_color }}";

  var invoiceLabels = {{ json_encode($account->getInvoiceLabels()) }};

  var isRefreshing = false;
  var needsRefresh = false;

  function refreshPDF() {
    console.log('refreshPDF');
    if ({{ Auth::check() && Auth::user()->force_pdfjs ? 'false' : 'true' }} && (isFirefox || (isChrome && !isChromium))) {
      var string = getPDFString();
      if (!string) return;
      $('#theFrame').attr('src', string).show();    
    } else {      
      if (isRefreshing) {
        needsRefresh = true;
        return;
      }
      var string = getPDFString();
      if (!string) return;
      isRefreshing = true;
      var pdfAsArray = convertDataURIToBinary(string);  
      PDFJS.getDocument(pdfAsArray).then(function getPdfHelloWorld(pdf) {

        pdf.getPage(1).then(function getPageHelloWorld(page) {
          var scale = 1.5;
          var viewport = page.getViewport(scale);

          var canvas = document.getElementById('theCanvas');
          var context = canvas.getContext('2d');
          canvas.height = viewport.height;
          canvas.width = viewport.width;

          page.render({canvasContext: context, viewport: viewport});
          $('#theCanvas').show();
          isRefreshing = false;
          if (needsRefresh) {
            needsRefresh = false;
            refreshPDF();
          }
        });
      }); 
    }
  }

</script>