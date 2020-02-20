<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
</head>

<style>
    html {
        line-height: 1.15;
        -webkit-text-size-adjust: 100%
    }

    body {
        margin: 0
    }

    main {
        display: block
    }

    h1 {
        font-size: 2em;
        margin: .67em 0
    }

    a {
        background-color: transparent
    }

    strong {
        font-weight: bolder
    }

    button,
    input {
        font-family: inherit;
        font-size: 100%;
        line-height: 1.15;
        margin: 0;
        overflow: visible
    }

    button {
        text-transform: none
    }

    [type=button],
    [type=reset],
    [type=submit],
    button {
        -webkit-appearance: button
    }

    [type=button]::-moz-focus-inner,
    [type=reset]::-moz-focus-inner,
    [type=submit]::-moz-focus-inner,
    button::-moz-focus-inner {
        border-style: none;
        padding: 0
    }

    [type=button]:-moz-focusring,
    [type=reset]:-moz-focusring,
    [type=submit]:-moz-focusring,
    button:-moz-focusring {
        outline: 1px dotted ButtonText
    }

    legend {
        color: inherit;
        display: table;
        max-width: 100%;
        white-space: normal
    }

    [type=checkbox],
    [type=radio],
    legend {
        box-sizing: border-box;
        padding: 0
    }

    [type=number]::-webkit-inner-spin-button,
    [type=number]::-webkit-outer-spin-button {
        height: auto
    }

    [type=search] {
        -webkit-appearance: textfield;
        outline-offset: -2px
    }

    [type=search]::-webkit-search-decoration {
        -webkit-appearance: none
    }

    ::-webkit-file-upload-button {
        -webkit-appearance: button;
        font: inherit
    }

    [hidden] {
        display: none
    }

    h1,
    h3,
    p {
        margin: 0
    }

    button {
        background-color: transparent;
        background-image: none;
        padding: 0
    }

    button:focus {
        outline: 1px dotted;
        outline: 5px auto -webkit-focus-ring-color
    }

    html {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, Noto Sans, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji;
        line-height: 1.5
    }

    *,
    :after,
    :before {
        box-sizing: border-box;
        border: 0 solid #e2e8f0
    }

    input::-webkit-input-placeholder {
        color: #a0aec0
    }

    input::-moz-placeholder {
        color: #a0aec0
    }

    input:-ms-input-placeholder {
        color: #a0aec0
    }

    input::-ms-input-placeholder {
        color: #a0aec0
    }

    input::placeholder {
        color: #a0aec0
    }

    [role=button],
    button {
        cursor: pointer
    }

    table {
        border-collapse: collapse
    }

    h1,
    h3 {
        font-size: inherit;
        font-weight: inherit
    }

    a {
        color: inherit;
        text-decoration: inherit
    }

    button,
    input {
        padding: 0;
        line-height: inherit;
        color: inherit
    }

    canvas,
    object {
        display: block;
        vertical-align: middle
    }

    .bg-white {
        background-color: #fff
    }

    .bg-gray-100 {
        background-color: #f7fafc
    }

    .bg-gray-200 {
        background-color: #edf2f7
    }

    .bg-gray-800 {
        background-color: #2d3748
    }

    .bg-gray-900 {
        background-color: #1a202c
    }

    .bg-blue-500 {
        background-color: #4299e1
    }

    .bg-blue-600 {
        background-color: #3182ce
    }

    .bg-blue-700 {
        background-color: #2b6cb0
    }

    .bg-blue-800 {
        background-color: #2c5282
    }

    .bg-blue-900 {
        background-color: #2a4365
    }

    .hover\:bg-blue-600:hover {
        background-color: #3182ce
    }

    .hover\:bg-blue-700:hover {
        background-color: #2b6cb0
    }

    .border-blue-100 {
        border-color: #ebf8ff
    }

    .border-blue-800 {
        border-color: #2c5282
    }

    .rounded {
        border-radius: .25rem
    }

    .border {
        border-width: 1px
    }

    .border-b {
        border-bottom-width: 1px
    }

    .flex {
        display: flex
    }

    .table {
        display: table
    }

    .flex-col {
        flex-direction: column
    }

    .items-center {
        align-items: center
    }

    .justify-center {
        justify-content: center
    }

    .font-sans {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, Noto Sans, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji
    }

    .font-semibold {
        font-weight: 600
    }

    .h-64 {
        height: 16rem
    }

    .leading-normal {
        line-height: 1.5
    }

    .leading-loose {
        line-height: 2
    }

    .my-8 {
        margin-top: 2rem;
        margin-bottom: 2rem
    }

    .mx-auto {
        margin-left: auto;
        margin-right: auto
    }

    .-mx-8 {
        margin-left: -2rem;
        margin-right: -2rem
    }

    .mt-4 {
        margin-top: 1rem
    }

    .mt-8 {
        margin-top: 2rem
    }

    .mb-8 {
        margin-bottom: 2rem
    }

    .-mt-8 {
        margin-top: -2rem
    }

    .max-w-md {
        max-width: 28rem
    }

    .min-h-screen {
        min-height: 100vh
    }

    .p-1 {
        padding: .25rem
    }

    .p-4 {
        padding: 1rem
    }

    .p-8 {
        padding: 2rem
    }

    .py-2 {
        padding-top: .5rem;
        padding-bottom: .5rem
    }

    .px-4 {
        padding-left: 1rem;
        padding-right: 1rem
    }

    .py-8 {
        padding-top: 2rem;
        padding-bottom: 2rem
    }

    .static {
        position: static
    }

    .hover\:shadow:hover,
    .shadow {
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, .1), 0 1px 2px 0 rgba(0, 0, 0, .06)
    }

    .table-auto {
        table-layout: auto
    }

    .text-left {
        text-align: left
    }

    .text-center {
        text-align: center
    }

    .text-white {
        color: #fff
    }

    .text-gray-200 {
        color: #edf2f7
    }

    .text-gray-700 {
        color: #4a5568
    }

    .text-blue-500 {
        color: #4299e1
    }

    .hover\:text-blue-600:hover {
        color: #3182ce
    }

    .text-sm {
        font-size: .875rem
    }

    .text-base {
        font-size: 1rem
    }

    .text-xl {
        font-size: 1.25rem
    }

    .text-4xl {
        font-size: 2.25rem
    }

    .uppercase {
        text-transform: uppercase
    }

    .underline {
        text-decoration: underline
    }

    .antialiased {
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale
    }

    .tracking-wide {
        letter-spacing: .025em
    }

    .w-full {
        width: 100%
    }

    @media (min-width:640px) {
        .sm\:text-lg {
            font-size: 1.125rem
        }
    }
</style>

<body class="antialiased font-sans bg-gray-200">
    <div class="app font-sans min-w-screen min-h-screen bg-grey-lighter py-8 px-4">

        <div class="max-w-md mx-auto">

            <div class="bg-white p-8 shadow">

                {{ $header }}

                <div class="py-8 border-b">
                    
                    @isset($greeting) 
                        {{ $greeting }} 
                    @endisset

                    <div class="mt-4">
                        {{ $slot }}
                    </div>

                    @isset($signature)
                    <p class="text-sm">Sincerely,</p>
                    <p class="text-sm">{{ $signature }}</p>
                    @endisset

                </div>

                @isset($footer)
                    {{ $footer }}
                @endisset

            </div>

            <div class="text-center text-sm mt-8">

                <div class="meta__help">
                    @isset($below_card)
                        {!! $below_card !!}
                    @endisset
                </div>

            </div>


        </div>

    </div>
</body>

</html>