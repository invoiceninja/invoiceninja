<div>
<div class="flex flex-col items-end mb-2">
  <button wire:loading.attr="disabled" wire:click="downloadPdf" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-2 rounded inline-flex">
      <span>{{ ctrans('texts.download_pdf') }}</span>
      <div wire:loading wire:target="downloadPdf">
          <svg class="animate-spin h-5 w-5 text-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>

      </div>
  </button>
</div>
<div class="hidden lg:block">
  <div wire:init="getPdf()">
      @if($pdf)
      <!-- <iframe id="pdf-iframe" src="{!! $pdf !!}" class="h-screen w-full border-0 mt-4"></iframe> -->
      <iframe id="pdf-iframe" src="/{{ $route_entity }}/showBlob/{{ $pdf }}" class="h-screen w-full border-0 mt-4"></iframe>
      @else
      <div class="flex mt-4 place-items-center">
          <span class="loader m-auto"></span>
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
      @endif
  </div>
</div>

<div class="block lg:hidden">
@include('portal.ninja2020.components.html-viewer')
</div>
</div>