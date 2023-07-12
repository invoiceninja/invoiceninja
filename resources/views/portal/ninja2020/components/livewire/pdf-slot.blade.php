<div>
<div class="hidden lg:block">
  <div wire:init="getPdf()">
      @if($pdf)
      <iframe id="pdf-iframe" src="{!! $pdf !!}" class="h-screen w-full border-0 mt-4"></iframe>
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

<div class="hidden md:block">
@include('portal.ninja2020.components.html-viewer')
</div>
</div>