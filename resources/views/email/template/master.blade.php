@php
  if(!isset($design)) 
    $design = 'light';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title')</title>
  <style>
  /*! normalize.css v8.0.1 | MIT License | github.com/necolas/normalize.css */html{line-height:1.15;-webkit-text-size-adjust:100%}body{margin:0}h1{font-size:2em;margin:.67em 0}a{background-color:transparent}img{border-style:none}button{font-family:inherit;font-size:100%;line-height:1.15;margin:0;overflow:visible;text-transform:none}[type=button],[type=reset],[type=submit],button{-webkit-appearance:button}[type=button]::-moz-focus-inner,[type=reset]::-moz-focus-inner,[type=submit]::-moz-focus-inner,button::-moz-focus-inner{border-style:none;padding:0}[type=button]:-moz-focusring,[type=reset]:-moz-focusring,[type=submit]:-moz-focusring,button:-moz-focusring{outline:1px dotted ButtonText}[type=checkbox],[type=radio]{box-sizing:border-box;padding:0}[type=number]::-webkit-inner-spin-button,[type=number]::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;outline-offset:-2px}[type=search]::-webkit-search-decoration{-webkit-appearance:none}::-webkit-file-upload-button{-webkit-appearance:button;font:inherit}[hidden]{display:none}h1,p{margin:0}button{background-color:transparent;background-image:none}button:focus{outline:1px dotted;outline:5px auto -webkit-focus-ring-color}html{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;line-height:1.5}*,:after,:before{box-sizing:border-box;border:0 solid #e2e8f0}img{border-style:solid}[role=button],button{cursor:pointer}h1{font-size:inherit;font-weight:inherit}a{text-decoration:inherit}a,button{color:inherit}button{padding:0;line-height:inherit}img,object,svg{display:block;vertical-align:middle}img{max-width:100%;height:auto}.bg-white{background-color:#fff}.bg-gray-200{background-color:#edf2f7}.bg-gray-800{background-color:#2d3748}.bg-gray-900{background-color:#1a202c}.bg-green-500{background-color:#48bb78}.bg-blue-500{background-color:#4299e1}.bg-blue-700{background-color:#2b6cb0}.bg-blue-900{background-color:#2a4365}.hover\:bg-green-600:hover{background-color:#38a169}.hover\:bg-blue-600:hover{background-color:#3182ce}.border-gray-800{border-color:#2d3748}.border-green-500{border-color:#48bb78}.border-blue-500{border-color:#4299e1}.rounded-lg{border-radius:.5rem}.border-t-2{border-top-width:2px}.border-b{border-bottom-width:1px}.flex{display:flex}.grid{display:grid}.flex-col{flex-direction:column}.items-center{align-items:center}.justify-center{justify-content:center}.font-sans{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji}.h-6{height:1.5rem}.h-32{height:8rem}.leading-tight{line-height:1.25}.my-4{margin-top:1rem;margin-bottom:1rem}.my-10{margin-top:2.5rem;margin-bottom:2.5rem}.mt-4{margin-top:1rem}.mb-4{margin-bottom:1rem}.mt-5{margin-top:1.25rem}.mt-8{margin-top:2rem}.mb-8{margin-bottom:2rem}.mt-10{margin-top:2.5rem}.p-6{padding:1.5rem}.py-3{padding-top:.75rem;padding-bottom:.75rem}.px-4{padding-left:1rem;padding-right:1rem}.py-6{padding-top:1.5rem;padding-bottom:1.5rem}.py-8{padding-top:2rem;padding-bottom:2rem}.px-10{padding-left:2.5rem;padding-right:2.5rem}.static{position:static}.shadow{box-shadow:0 1px 3px 0 rgba(0,0,0,.1),0 1px 2px 0 rgba(0,0,0,.06)}.text-center{text-align:center}.text-white{color:#fff}.text-gray-400{color:#cbd5e0}.text-gray-700{color:#4a5568}.text-green-700{color:#2f855a}.hover\:text-green-800:hover{color:#276749}.text-2xl{font-size:1.5rem}.grid-cols-6{grid-template-columns:repeat(6,minmax(0,1fr))}.col-span-4{grid-column:span 4/span 4}.col-start-2{grid-column-start:2}.transform{--transform-translate-x:0;--transform-translate-y:0;--transform-rotate:0;--transform-skew-x:0;--transform-skew-y:0;--transform-scale-x:1;--transform-scale-y:1;transform:translateX(var(--transform-translate-x)) translateY(var(--transform-translate-y)) rotate(var(--transform-rotate)) skewX(var(--transform-skew-x)) skewY(var(--transform-skew-y)) scaleX(var(--transform-scale-x)) scaleY(var(--transform-scale-y))}p{margin-top:1rem}@media (min-width:768px){.md\:text-3xl{font-size:1.875rem}}
  </style>
</head>

@if($design == 'dark')
  <style>
    * {
      color: #cbd5e0 !important;
    }
  </style>
@endif

<body class="{{ $design == 'light' ? 'bg-gray-200' : 'bg-gray-800' }} my-10 font-sans {{ $design == 'light' ? 'text-gray-700' : 'text-gray-400' }}">
<div class="grid grid-cols-6">
        <div class="col-start-2 col-span-4">
            <div class="{{ $design == 'light' ? 'bg-white' : 'bg-gray-900' }} shadow border-t-2 {{ $design == 'light' ? 'border-green-500' : 'border-gray-800' }}">
                {{ $header }}
                <div id="text" class="px-10 py-6 flex flex-col">
                    @isset($greeting)
                      <p>{{ $greeting }}</p>
                    @endisset
                    <p>
                      {{ $slot }}
                    </p>
                    @isset($signature)
                      <p>{{ $signature }}</p>
                    @endisset
                </div>
            </div>

            @isset($below_card)
            <div id="bottomText" class="text-center my-4 px-10">
                {{ $below_card }}
            </div>
            @endisset

        </div>
    </div>
</body>
</html>