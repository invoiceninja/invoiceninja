<iframe id="theFrame" style="display:block" frameborder="1" width="100%" height="{{ isset($pdfHeight) ? $pdfHeight : 1180 }}px"></iframe>
<canvas id="theCanvas" style="display:none;width:100%;border:solid 1px #CCCCCC;"></canvas>

@if (!Utils::isNinja() || !Utils::isPro())
<div class="modal fade" id="moreDesignsModal" tabindex="-1" role="dialog" aria-labelledby="moreDesignsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">{{ trans('texts.more_designs_title') }}</h4>
      </div>

      <div class="container">
        @if (Utils::isNinja())
          <h3>{{ trans('texts.more_designs_cloud_header') }}</h3>
          <p>{{ trans('texts.more_designs_cloud_text') }}</p>
        @else
          <h3>{{ trans('texts.more_designs_self_host_header', ['price' => INVOICE_DESIGNS_PRICE]) }}</h3>
          <p>{{ trans('texts.more_designs_self_host_text') }}</p>
        @endif
      </div>

      <center id="designThumbs">
        <p>&nbsp;</p>
        <a href="{{ asset('/images/designs/business.png') }}" data-lightbox="more-designs" data-title="Business">
            <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('/images/designs/business_thumb.png') }}"/>
        </a>&nbsp;&nbsp;&nbsp;&nbsp;
        <a href="{{ asset('/images/designs/creative.png') }}" data-lightbox="more-designs" data-title="Creative">
            <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('/images/designs/creative_thumb.png') }}"/>
        </a>&nbsp;&nbsp;&nbsp;&nbsp;
        <a href="{{ asset('/images/designs/elegant.png') }}" data-lightbox="more-designs" data-title="Elegant">
            <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('/images/designs/elegant_thumb.png') }}"/>
        </a>
        <p>&nbsp;</p>
        <a href="{{ asset('/images/designs/hipster.png') }}" data-lightbox="more-designs" data-title="Hipster">
            <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('/images/designs/hipster_thumb.png') }}"/>
        </a>&nbsp;&nbsp;&nbsp;&nbsp;
        <a href="{{ asset('/images/designs/playful.png') }}" data-lightbox="more-designs" data-title="Playful">
            <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('/images/designs/playful_thumb.png') }}"/>
        </a>&nbsp;&nbsp;&nbsp;&nbsp;
        <a href="{{ asset('/images/designs/photo.png') }}" data-lightbox="more-designs" data-title="Photo">
            <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('/images/designs/photo_thumb.png') }}"/>
        </a>
        <p>&nbsp;</p>
      </center>

      <div class="modal-footer" id="signUpFooter">
        <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>

        @if (Utils::isNinjaProd())
          <a class="btn btn-primary" href="javascript:showUpgradeModal()">{{ trans('texts.go_pro') }}</a>
        @else
          <button type="button" class="btn btn-primary" onclick="buyProduct('{{ INVOICE_DESIGNS_AFFILIATE_KEY }}', '{{ PRODUCT_INVOICE_DESIGNS }}')">{{ trans('texts.buy') }}</button>
        @endif
      </div>
    </div>
  </div>
</div>
@endif


<script type="text/javascript">
  window.logoImages = {};

  logoImages.imageLogo1 = "{{ Form::image_data('images/report_logo1.jpg') }}";
  logoImages.imageLogoWidth1 =120;
  logoImages.imageLogoHeight1 = 40

  logoImages.imageLogo2 = "{{ Form::image_data('images/report_logo2.jpg') }}";
  logoImages.imageLogoWidth2 =325/2;
  logoImages.imageLogoHeight2 = 81/2;

  logoImages.imageLogo3 = "{{ Form::image_data('images/report_logo3.png') }}";
  logoImages.imageLogoWidth3 =325/2;
  logoImages.imageLogoHeight3 = 81/2;

  @if ($account->hasLogo())
  window.accountLogo = "{{ Form::image_data($account->getLogoRaw(), true) }}";
  if (window.invoice) {
    invoice.image = window.accountLogo;
    invoice.imageWidth = {{ $account->getLogoWidth() }};
    invoice.imageHeight = {{ $account->getLogoHeight() }};
  }
  @endif

  var NINJA = NINJA || {};
  @if ($account->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN))
      NINJA.primaryColor = "{{ $account->primary_color }}";
      NINJA.secondaryColor = "{{ $account->secondary_color }}";
      NINJA.fontSize = {{ $account->font_size }};
      NINJA.headerFont = {!! json_encode($account->getHeaderFontName()) !!};
      NINJA.bodyFont = {!! json_encode($account->getBodyFontName()) !!};
  @else
      NINJA.primaryColor = "";
      NINJA.secondaryColor = "";
      NINJA.fontSize = 9;
      NINJA.headerFont = "Roboto";
      NINJA.bodyFont = "Roboto";
  @endif

  var invoiceLabels = {!! json_encode($account->getInvoiceLabels()) !!};

  if (window.invoice) {
    //invoiceLabels.item = invoice.has_tasks ? invoiceLabels.date : invoiceLabels.item_orig;
    invoiceLabels.quantity = invoice.has_tasks ? invoiceLabels.hours : invoiceLabels.quantity_orig;
    invoiceLabels.unit_cost = invoice.has_tasks ? invoiceLabels.rate : invoiceLabels.unit_cost_orig;
  }

  var isRefreshing = false;
  var needsRefresh = false;

  function refreshPDF(force) {
    //console.log('refresh PDF - force: ' + force + ' ' + (new Date()).getTime())
    return getPDFString(refreshPDFCB, force);
  }

  function refreshPDFCB(string) {
    if (!string) return;
    PDFJS.workerSrc = '{{ asset('js/pdf_viewer.worker.js') }}';
    var forceJS = {{ Auth::check() && Auth::user()->force_pdfjs ? 'false' : 'true' }};
    // Temporarily workaround for: https://code.google.com/p/chromium/issues/detail?id=574648
    if (forceJS && (isFirefox || (isChrome && (!isChrome48 || {{ isset($viewPDF) && $viewPDF ? 'true' : 'false' }})))) {
      $('#theFrame').attr('src', string).show();
    } else {
      if (isRefreshing) {
        needsRefresh = true;
        return;
      }
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
          $('#theFrame').hide();
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

  function showMoreDesigns() {
    loadImages('#designThumbs');
    trackEvent('/account', '/view_more_designs');
    $('#moreDesignsModal').modal('show');
  }

</script>
