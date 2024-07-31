<div>
  <div class="flex flex-row space-x-2 float-right mb-2" x-data>
    <button wire:loading.attr="disabled" wire:click="downloadPdf" class="button bg-primary text-white px-4 py-4 lg:px-2 lg:py-2 rounded" type="button">
        <span class="mr-0">{{ ctrans('texts.download_pdf') }}</span>
        <div wire:loading wire:target="downloadPdf">
            <svg class="animate-spin h-5 w-5 text-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </button>
    @if($entity_type == 'invoice' && $settings->enable_e_invoice)
    <button wire:loading.attr="disabled" wire:click="downloadEDocument" class="button bg-primary text-white px-4 py-4 lg:px-2 lg:py-2 rounded" type="button">
        <span>{{ ctrans('texts.download_e_invoice') }}</span>
        <div wire:loading wire:target="downloadEDocument">
            <svg class="animate-spin h-5 w-5 text-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </button>
    @endif
      @if($entity_type == 'credit' && $settings->enable_e_invoice)
          <button wire:loading.attr="disabled" wire:click="downloadEDocument" class="button bg-primary text-white px-4 py-4 lg:px-2 lg:py-2 rounded" type="button">
              <span>{{ ctrans('texts.download_e_credit') }}</span>
              <div wire:loading wire:target="downloadEDocument">
                  <svg class="animate-spin h-5 w-5 text-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
              </div>
          </button>
      @endif
      @if($entity_type == 'quote' && $settings->enable_e_invoice)
          <button wire:loading.attr="disabled" wire:click="downloadEDocument" class="button bg-primary text-white px-4 py-4 lg:px-2 lg:py-2 rounded" type="button">
              <span>{{ ctrans('texts.download_e_quote') }}</span>
              <div wire:loading wire:target="downloadEDocument">
                  <svg class="animate-spin h-5 w-5 text-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
              </div>
          </button>
      @endif
{{--      Not implemented yet--}}
{{--      @if($entity_type == 'purchase_order' && $settings->enable_e_invoice)
          <button wire:loading.attr="disabled" wire:click="downloadEInvoice" class="button bg-primary text-white px-4 py-4 lg:px-2 lg:py-2 rounded" type="button">
              <span>{{ ctrans('texts.download_e_invoice') }}</span>
              <div wire:loading wire:target="downloadEInvoice">
                  <svg class="animate-spin h-5 w-5 text-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
              </div>
          </button>
      @endif--}}
  </div>
  @if($html_entity_option)
  <div class="hidden lg:block">
  @else
  <div>
  @endif
    <div wire:init="getPdf()">
        <div class="flex mt-4 place-items-center" id="loader" wire:ignore>
            <span class="loader m-auto" wire:ignore></span>
            <style type="text/css">
            .loader {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            position: relative;
            animation: rotate 1s linear infinite
          }
          .loader::before , .loader::after {
            content: "";
            box-sizing: border-box;
            position: absolute;
            inset: 0px;
            border-radius: 50%;
            border: 5px solid #454545;
            animation: prixClipFix 2s linear infinite ;
          }
          .loader::after{
            border-color: #FF3D00;
            animation: prixClipFix 2s linear infinite , rotate 0.5s linear infinite reverse;
            inset: 6px;
          }
          @keyframes rotate {
            0%   {transform: rotate(0deg)}
            100%   {transform: rotate(360deg)}
          }
          @keyframes prixClipFix {
              0%   {clip-path:polygon(50% 50%,0 0,0 0,0 0,0 0,0 0)}
              25%  {clip-path:polygon(50% 50%,0 0,100% 0,100% 0,100% 0,100% 0)}
              50%  {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,100% 100%,100% 100%)}
              75%  {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,0 100%,0 100%)}
              100% {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,0 100%,0 0)}
          }
          </style>
        </div>
        @if($pdf)
        <iframe id="pdf-iframe" src="/{{ $route_entity }}/showBlob/{{ $pdf }}" class="h-screen w-full border-0 mt-4"></iframe>
        @endif
    </div>
  </div>

  @if($html_entity_option)
  <div class="block lg:hidden">
    @include('portal.ninja2020.components.html-viewer')
  </div>
  @endif

</div>

<script type="text/javascript">

  waitForElement("#pdf-iframe", 0).then(function(){
    const iframe = document.getElementById("pdf-iframe");

    iframe.addEventListener("load", function () {
      const loader = document.getElementById("loader")
      loader.classList.add("hidden");
    });

  });

function waitForElement(querySelector, timeout){
  return new Promise((resolve, reject)=>{
    var timer = false;
    if(document.querySelectorAll(querySelector).length) return resolve();
    const observer = new MutationObserver(()=>{
      if(document.querySelectorAll(querySelector).length){
        observer.disconnect();
        if(timer !== false) clearTimeout(timer);
        return resolve();
      }
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
    if(timeout) timer = setTimeout(()=>{
      observer.disconnect();
      reject();
    }, timeout);
  });

}

</script>
