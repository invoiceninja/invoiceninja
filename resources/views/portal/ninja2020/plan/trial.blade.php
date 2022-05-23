@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.account_management'))

@section('body')
<style>
  @import url('https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&display=swap');

/*
! tailwindcss v3.0.24 | MIT License | https://tailwindcss.com
*/

/*
1. Prevent padding and border from affecting element width. (https://github.com/mozdevs/cssremedy/issues/4)
2. Allow adding a border to an element by just adding a border-width. (https://github.com/tailwindcss/tailwindcss/pull/116)
*/

*,
::before,
::after {
  box-sizing: border-box;
  /* 1 */
  border-width: 0;
  /* 2 */
  border-style: solid;
  /* 2 */
  border-color: #e5e7eb;
  /* 2 */
}

::before,
::after {
  --tw-content: '';
}

/*
1. Use a consistent sensible line-height in all browsers.
2. Prevent adjustments of font size after orientation changes in iOS.
3. Use a more readable tab size.
4. Use the user's configured `sans` font-family by default.
*/

html {
  line-height: 1.5;
  /* 1 */
  -webkit-text-size-adjust: 100%;
  /* 2 */
  -moz-tab-size: 4;
  /* 3 */
  -o-tab-size: 4;
     tab-size: 4;
  /* 3 */
  /* 4 */
}

.main_layout {
  /*background-color: white;*/
}
/*
1. Remove the margin in all browsers.
2. Inherit line-height from `html` so users can set them as a class directly on the `html` element.
*/

body {
  margin: 0;
  /* 1 */
  line-height: inherit;
  /* 2 */
}

/*
1. Add the correct height in Firefox.
2. Correct the inheritance of border color in Firefox. (https://bugzilla.mozilla.org/show_bug.cgi?id=190655)
3. Ensure horizontal rules are visible by default.
*/

hr {
  height: 0;
  /* 1 */
  color: inherit;
  /* 2 */
  border-top-width: 1px;
  /* 3 */
}

/*
Add the correct text decoration in Chrome, Edge, and Safari.
*/

abbr:where([title]) {
  -webkit-text-decoration: underline dotted;
          text-decoration: underline dotted;
}

/*
Remove the default font size and weight for headings.
*/

h1,
h2,
h3,
h4,
h5,
h6 {
  font-size: inherit;
  font-weight: inherit;
}

/*
Reset links to optimize for opt-in styling instead of opt-out.
*/

a {
  color: inherit;
  text-decoration: inherit;
}

/*
Add the correct font weight in Edge and Safari.
*/

b,
strong {
  font-weight: bolder;
}

/*
1. Use the user's configured `mono` font family by default.
2. Correct the odd `em` font sizing in all browsers.
*/

code,
kbd,
samp,
pre {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  /* 1 */
  font-size: 1em;
  /* 2 */
}

/*
Add the correct font size in all browsers.
*/

small {
  font-size: 80%;
}

/*
Prevent `sub` and `sup` elements from affecting the line height in all browsers.
*/

sub,
sup {
  font-size: 75%;
  line-height: 0;
  position: relative;
  vertical-align: baseline;
}

sub {
  bottom: -0.25em;
}

sup {
  top: -0.5em;
}

/*
1. Remove text indentation from table contents in Chrome and Safari. (https://bugs.chromium.org/p/chromium/issues/detail?id=999088, https://bugs.webkit.org/show_bug.cgi?id=201297)
2. Correct table border color inheritance in all Chrome and Safari. (https://bugs.chromium.org/p/chromium/issues/detail?id=935729, https://bugs.webkit.org/show_bug.cgi?id=195016)
3. Remove gaps between table borders by default.
*/

table {
  text-indent: 0;
  /* 1 */
  border-color: inherit;
  /* 2 */
  border-collapse: collapse;
  /* 3 */
}

/*
1. Change the font styles in all browsers.
2. Remove the margin in Firefox and Safari.
3. Remove default padding in all browsers.
*/

button,
input,
optgroup,
select,
textarea {
  font-family: inherit;
  /* 1 */
  font-size: 100%;
  /* 1 */
  line-height: inherit;
  /* 1 */
  color: inherit;
  /* 1 */
  margin: 0;
  /* 2 */
  padding: 0;
  /* 3 */
}

/*
Remove the inheritance of text transform in Edge and Firefox.
*/

button,
select {
  text-transform: none;
}

/*
1. Correct the inability to style clickable types in iOS and Safari.
2. Remove default button styles.
*/

button,
[type='button'],
[type='reset'],
[type='submit'] {
  -webkit-appearance: button;
  /* 1 */
  background-color: transparent;
  /* 2 */
  background-image: none;
  /* 2 */
}

/*
Use the modern Firefox focus style for all focusable elements.
*/

:-moz-focusring {
  outline: auto;
}

/*
Remove the additional `:invalid` styles in Firefox. (https://github.com/mozilla/gecko-dev/blob/2f9eacd9d3d995c937b4251a5557d95d494c9be1/layout/style/res/forms.css#L728-L737)
*/

:-moz-ui-invalid {
  box-shadow: none;
}

/*
Add the correct vertical alignment in Chrome and Firefox.
*/

progress {
  vertical-align: baseline;
}

/*
Correct the cursor style of increment and decrement buttons in Safari.
*/

::-webkit-inner-spin-button,
::-webkit-outer-spin-button {
  height: auto;
}

/*
1. Correct the odd appearance in Chrome and Safari.
2. Correct the outline style in Safari.
*/

[type='search'] {
  -webkit-appearance: textfield;
  /* 1 */
  outline-offset: -2px;
  /* 2 */
}

/*
Remove the inner padding in Chrome and Safari on macOS.
*/

::-webkit-search-decoration {
  -webkit-appearance: none;
}

/*
1. Correct the inability to style clickable types in iOS and Safari.
2. Change font properties to `inherit` in Safari.
*/

::-webkit-file-upload-button {
  -webkit-appearance: button;
  /* 1 */
  font: inherit;
  /* 2 */
}

/*
Add the correct display in Chrome and Safari.
*/

summary {
  display: list-item;
}

/*
Removes the default spacing and border for appropriate elements.
*/

blockquote,
dl,
dd,
h1,
h2,
h3,
h4,
h5,
h6,
hr,
figure,
p,
pre {
  margin: 0;
}

fieldset {
  margin: 0;
  padding: 0;
}

legend {
  padding: 0;
}

ol,
ul,
menu {
  list-style: none;
  margin: 0;
  padding: 0;
}

/*
Prevent resizing textareas horizontally by default.
*/

textarea {
  resize: vertical;
}

/*
1. Reset the default placeholder opacity in Firefox. (https://github.com/tailwindlabs/tailwindcss/issues/3300)
2. Set the default placeholder color to the user's configured gray 400 color.
*/

input::-moz-placeholder, textarea::-moz-placeholder {
  opacity: 1;
  /* 1 */
  color: #9ca3af;
  /* 2 */
}

input:-ms-input-placeholder, textarea:-ms-input-placeholder {
  opacity: 1;
  /* 1 */
  color: #9ca3af;
  /* 2 */
}

input::placeholder,
textarea::placeholder {
  opacity: 1;
  /* 1 */
  color: #9ca3af;
  /* 2 */
}

/*
Set the default cursor for buttons.
*/

button,
[role="button"] {
  cursor: pointer;
}

/*
Make sure disabled buttons don't get the pointer cursor.
*/

:disabled {
  cursor: default;
}

/*
1. Make replaced elements `display: block` by default. (https://github.com/mozdevs/cssremedy/issues/14)
2. Add `vertical-align: middle` to align replaced elements more sensibly by default. (https://github.com/jensimmons/cssremedy/issues/14#issuecomment-634934210)
   This can trigger a poorly considered lint error in some tools but is included by design.
*/

img,
svg,
video,
canvas,
audio,
iframe,
embed,
object {
  display: block;
  /* 1 */
  vertical-align: middle;
  /* 2 */
}

/*
Constrain images and videos to the parent width and preserve their intrinsic aspect ratio. (https://github.com/mozdevs/cssremedy/issues/14)
*/

img,
video {
  max-width: 100%;
  height: auto;
}

/*
Ensure the default browser behavior of the `hidden` attribute.
*/

[hidden] {
  display: none;
}

*, ::before, ::after{
  --tw-translate-x: 0;
  --tw-translate-y: 0;
  --tw-rotate: 0;
  --tw-skew-x: 0;
  --tw-skew-y: 0;
  --tw-scale-x: 1;
  --tw-scale-y: 1;
  --tw-pan-x:  ;
  --tw-pan-y:  ;
  --tw-pinch-zoom:  ;
  --tw-scroll-snap-strictness: proximity;
  --tw-ordinal:  ;
  --tw-slashed-zero:  ;
  --tw-numeric-figure:  ;
  --tw-numeric-spacing:  ;
  --tw-numeric-fraction:  ;
  --tw-ring-inset:  ;
  --tw-ring-offset-width: 0px;
  --tw-ring-offset-color: #fff;
  --tw-ring-color: rgb(59 130 246 / 0.5);
  --tw-ring-offset-shadow: 0 0 #0000;
  --tw-ring-shadow: 0 0 #0000;
  --tw-shadow: 0 0 #0000;
  --tw-shadow-colored: 0 0 #0000;
  --tw-blur:  ;
  --tw-brightness:  ;
  --tw-contrast:  ;
  --tw-grayscale:  ;
  --tw-hue-rotate:  ;
  --tw-invert:  ;
  --tw-saturate:  ;
  --tw-sepia:  ;
  --tw-drop-shadow:  ;
  --tw-backdrop-blur:  ;
  --tw-backdrop-brightness:  ;
  --tw-backdrop-contrast:  ;
  --tw-backdrop-grayscale:  ;
  --tw-backdrop-hue-rotate:  ;
  --tw-backdrop-invert:  ;
  --tw-backdrop-opacity:  ;
  --tw-backdrop-saturate:  ;
  --tw-backdrop-sepia:  ;
}

.absolute{
  position: absolute;
}

.relative{
  position: relative;
}

.z-0{
  z-index: 0;
}

.z-10{
  z-index: 10;
}

.m-0{
  margin: 0px;
}

.mx-\[auto\]{
  margin-left: auto;
  margin-right: auto;
}

.mb-\[30px\]{
  margin-bottom: 30px;
}

.mb-\[11px\]{
  margin-bottom: 11px;
}

.mt-\[30px\]{
  margin-top: 30px;
}

.mb-\[21px\]{
  margin-bottom: 21px;
}

.mb-4{
  margin-bottom: 1rem;
}

.mb-\[10px\]{
  margin-bottom: 10px;
}

.mt-5{
  margin-top: 1.25rem;
}

.mt-\[50px\]{
  margin-top: 50px;
}

.mb-\[50px\]{
  margin-bottom: 50px;
}

.mb-\[8px\]{
  margin-bottom: 8px;
}

.ml-\[5px\]{
  margin-left: 5px;
}

.mb-\[24px\]{
  margin-bottom: 24px;
}

.mt-\[auto\]{
  margin-top: auto;
}

.mb-\[26px\]{
  margin-bottom: 26px;
}

.block{
  display: block;
}

.flex{
  display: flex;
}

.hidden{
  display: none;
}

.min-h-\[450px\]{
  min-height: 450px;
}

.w-\[100\%\]{
  width: 100%;
}

.w-full{
  width: 100%;
}

.max-w-\[625px\]{
  max-width: 625px;
}

.max-w-\[212px\]{
  max-width: 212px;
}

.flex-1{
  flex: 1 1 0%;
}

.grow{
  flex-grow: 1;
}

.transform{
  transform: translate(var(--tw-translate-x), var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y));
}

.flex-col{
  flex-direction: column;
}

.flex-col-reverse{
  flex-direction: column-reverse;
}

.justify-end{
  justify-content: flex-end;
}

.justify-center{
  justify-content: center;
}

.justify-between{
  justify-content: space-between;
}

.gap-\[25px\]{
  gap: 25px;
}

.gap-\[13px\]{
  gap: 13px;
}

.gap-\[10px\]{
  gap: 10px;
}

.gap-\[44px\]{
  gap: 44px;
}

.gap-x-2{
  -moz-column-gap: 0.5rem;
       column-gap: 0.5rem;
}

.gap-y-\[20px\]{
  row-gap: 20px;
}

.overflow-hidden{
  overflow: hidden;
}

.overflow-y-auto{
  overflow-y: auto;
}

.rounded{
  border-radius: 0.25rem;
}

.rounded-sm{
  border-radius: 5px;
}

.border{
  border-width: 1px;
}

.border-t-\[0px\]{
  border-top-width: 0px;
}

.border-t-\[10px\]{
  border-top-width: 10px;
}

.border-t-\[1px\]{
  border-top-width: 1px;
}

.border-t-\[11px\]{
  border-top-width: 11px;
}

.border-light-grey{
  --tw-border-opacity: 1;
  border-color: rgb(232 233 237 / var(--tw-border-opacity));
}

.border-primary-blue{
  --tw-border-opacity: 1;
  border-color: rgb(0 145 234 / var(--tw-border-opacity));
}

.border-\[transparent\]{
  border-color: transparent;
}

.bg-secondary-dark{
  --tw-bg-opacity: 1;
  background-color: rgb(73 68 68 / var(--tw-bg-opacity));
}

.bg-white{
  --tw-bg-opacity: 1;
  background-color: rgb(255 255 255 / var(--tw-bg-opacity));
}

.bg-primary-blue{
  --tw-bg-opacity: 1;
  background-color: rgb(0 145 234 / var(--tw-bg-opacity));
}

.bg-clip-padding{
  background-clip: padding-box;
}

.p-4{
  padding: 1rem;
}

.p-\[20px\]{
  padding: 20px;
}

.py-\[36px\]{
  padding-top: 36px;
  padding-bottom: 36px;
}

.px-\[20px\]{
  padding-left: 20px;
  padding-right: 20px;
}

.py-\[33px\]{
  padding-top: 33px;
  padding-bottom: 33px;
}

.px-3{
  padding-left: 0.75rem;
  padding-right: 0.75rem;
}

.py-2{
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
}

.py-\[9\.5px\]{
  padding-top: 9.5px;
  padding-bottom: 9.5px;
}

.px-\[12px\]{
  padding-left: 12px;
  padding-right: 12px;
}

.py-\[22px\]{
  padding-top: 22px;
  padding-bottom: 22px;
}

.px-\[22px\]{
  padding-left: 22px;
  padding-right: 22px;
}

.pt-\[20px\]{
  padding-top: 20px;
}

.pl-\[18px\]{
  padding-left: 18px;
}

.pr-\[18px\]{
  padding-right: 18px;
}

.pb-\[20px\]{
  padding-bottom: 20px;
}

.pt-\[17px\]{
  padding-top: 17px;
}

.pb-\[23px\]{
  padding-bottom: 23px;
}

.pt-\[21px\]{
  padding-top: 21px;
}

.pb-\[26px\]{
  padding-bottom: 26px;
}

.text-left{
  text-align: left;
}

.text-center{
  text-align: center;
}

.font-\[\'Open_Sans\'\]{
  font-family: 'Open Sans';
}

.text-\[15px\]{
  font-size: 15px;
}

.text-\[24px\]{
  font-size: 24px;
}

.text-\[18px\]{
  font-size: 18px;
}

.text-base{
  font-size: 1rem;
  line-height: 1.5rem;
}

.text-sm{
  font-size: 0.875rem;
  line-height: 1.25rem;
}

.text-\[12px\]{
  font-size: 12px;
}

.text-\[40px\]{
  font-size: 40px;
}

.text-\[14px\]{
  font-size: 14px;
}

.font-normal{
  font-weight: 400;
}

.font-semibold{
  font-weight: 600;
}

.font-bold{
  font-weight: 700;
}

.uppercase{
  text-transform: uppercase;
}

.italic{
  font-style: italic;
}

.leading-\[1\.75em\]{
  line-height: 1.75em;
}

.leading-\[1\.3em\]{
  line-height: 1.3em;
}

.leading-\[1\.35em\]{
  line-height: 1.35em;
}

.leading-\[1\.36em\]{
  line-height: 1.36em;
}

.leading-\[1\.5em\]{
  line-height: 1.5em;
}

.text-white{
  --tw-text-opacity: 1;
  color: rgb(255 255 255 / var(--tw-text-opacity));
}

.text-primary-blue{
  --tw-text-opacity: 1;
  color: rgb(0 145 234 / var(--tw-text-opacity));
}

.text-gray-700{
  --tw-text-opacity: 1;
  color: rgb(55 65 81 / var(--tw-text-opacity));
}

.text-dark-grey{
  --tw-text-opacity: 1;
  color: rgb(142 147 167 / var(--tw-text-opacity));
}

.text-light-grey-secondary{
  --tw-text-opacity: 1;
  color: rgb(219 220 222 / var(--tw-text-opacity));
}

.transition{
  transition-property: color, background-color, border-color, fill, stroke, opacity, box-shadow, transform, filter, -webkit-text-decoration-color, -webkit-backdrop-filter;
  transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
  transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter, -webkit-text-decoration-color, -webkit-backdrop-filter;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}

.duration-300{
  transition-duration: 300ms;
}

.ease-in-out{
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

.ease-in{
  transition-timing-function: cubic-bezier(0.4, 0, 1, 1);
}

/* UI Elements */

.button-primary {
  width: 100%;
  height: 49px;
  color: #fff;
  cursor: pointer;
}

.list-checkmark-round {
  padding: 0;
  list-style: none;
}

.list-checkmark-round li {
  position: relative;
  padding-left: 40px;
}

.list-checkmark-round li::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0px;
  width: 30px;
  height: 30px;
  background: url('/images/checkmark-round.svg') center/contain no-repeat;
}

.list-checkmark {
  padding: 0;
  list-style: none;
}

.list-checkmark li {
  position: relative;
  padding-left: 25px;
}

.list-checkmark li::before {
  content: "";
  position: absolute;
  left: 0;
  top: 4px;
  width: 16px;
  height: 16px;
  background: url('/images/checkmark.svg') center/contain no-repeat;
}

/* Main Content */

.pro-plan-trial {
  position: relative;
  overflow: hidden;
}

.pro-plan-trial::before {
  content: "";
  position: absolute;
  right: 0;
  bottom: 0;
  width: 601px;
  height: 601px;
  background: #0091EA;
  border-radius: 50%;
  transform: translate(129px, 449px);
  z-index: 1;
}

.success-banner {
  position: relative;
  overflow: hidden;
}

.success-banner::before{
  content: "";
  position: absolute;
  left: 0%;
  bottom: 0;
  width: 60%;
  height: 100%;
  background: url(/images/test.svg) right center/cover no-repeat;
  z-index: 1;
}

@media (max-width: 767px) {
  .success-banner::before {
    width: 100%;
  }
}

.hover\:opacity-80:hover{
  opacity: 0.8;
}

.focus\:border-primary-blue:focus{
  --tw-border-opacity: 1;
  border-color: rgb(0 145 234 / var(--tw-border-opacity));
}

.focus\:outline-none:focus{
  outline: 2px solid transparent;
  outline-offset: 2px;
}

@media (min-width: 768px){
  .md\:mx-\[0\]{
    margin-left: 0;
    margin-right: 0;
  }

  .md\:mb-\[46px\]{
    margin-bottom: 46px;
  }

  .md\:flex{
    display: flex;
  }

  .md\:w-1\/2{
    width: 50%;
  }

  .md\:w-1\/3{
    width: 33.333333%;
  }

  .md\:flex-row{
    flex-direction: row;
  }

  .md\:gap-\[18px\]{
    gap: 18px;
  }

  .md\:gap-x-\[21px\]{
    -moz-column-gap: 21px;
         column-gap: 21px;
  }

  .md\:px-\[58px\]{
    padding-left: 58px;
    padding-right: 58px;
  }

  .md\:pl-\[61px\]{
    padding-left: 61px;
  }

  .md\:pr-\[20px\]{
    padding-right: 20px;
  }

  .md\:text-left{
    text-align: left;
  }

  .md\:text-\[30px\]{
    font-size: 30px;
  }
}
</style>

<meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey()}}">
    <meta name="client-postal-code" content="{{ $client->postal_code }}">
    <meta name="client-name" content="{{ $client->present()->name() }}">

      <div class="flex flex-col gap-[25px] justify-stretch overflow-hidden md:flex-row">
        <div class="w-1/1 flex flex-col overflow-hidden md:flex-row md:w-1/2 md:flex ">
          <div
            class="w-[100%] pro-plan-trial py-[36px] px-[20px] bg-secondary-dark rounded text-white md:pl-[61px] md:pr-[20px]"
          >
            <h2 class="text-[24px] leading-[1.3em] font-semibold mb-[30px] relative z-10">
              Enjoy 14 days of our Pro Plan
            </h2>
            <ul class="list-checkmark-round relative z-10">
              <li class="mb-[11px]">Unlimited Clients & Invoices & Quotes</li>
              <li class="mb-[11px]">Remove "Created by Invoice Ninja"</li>
              <li class="mb-[11px]">10 Professional Invoice & Quote Templates</li>
              <li class="mb-[11px]">Send Invoice Emails Sent via Your Gmail</li>
              <li class="mb-[11px]">Attach Invoice PDF's to Client Emails</li>
              <li class="mb-[11px]">Customize Auto-Reminder Emails</li>
              <li class="mb-[11px]">Display Client E-Signatures on Invoices</li>
              <li>Enable a Client "Approve Terms' Checkbox</li>
            </ul>
            <p class="mt-[30px] font-semibold text-[18px] italic relative z-10">
              &amp; Much More!
            </p>
          </div>
        </div>
        <div class="w-1/1 flex flex-col overflow-hidden md:flex-row md:w-1/2 md:flex ">
          <div
            class="w-[100%] flex flex-col py-[33px] px-[20px] border border-light-grey rounded md:px-[58px]"
          >
            <h2 class="text-primary-blue text-[24px] leading-[1.3em] font-semibold mb-[21px]">
              Start your 14 day Pro Trial!
            </h2>
              
              <form id="card-form" action="{{ route('client.trial.response') }}" method="post">
              @csrf
              <input type="hidden" name="gateway_response" />
              <div class="alert alert-failure mb-4" hidden="" id="errors"></div>
              <div class="form-group mb-[10px]">
                <input
                  type="text"
                  class="form-control block w-full px-3 py-2 text-base font-normal text-gray-700 bg-white bg-clip-padding border border-light-grey rounded transition ease-in-out m-0 focus:primary-blue focus:outline-none"
                  id="name"
                  placeholder="{{ ctrans('texts.name') }}"
                  name="name"
                  value="{{$client->present()->name()}}">
                
              </div>
              <div class="form-group mb-[10px]">
                <input
                  type="text"
                  class="form-control block w-full py-[9.5px] px-[12px] text-base font-normal text-gray-700 bg-white bg-clip-padding border border-light-grey rounded transition ease-in-out m-0 focus:border-primary-blue focus:outline-none"
                  id="address1"
                  placeholder="{{ ctrans('texts.address1') }}"
                  name="address1"
                  value="{{$client->address1}}">
                
              </div>
              <div class="form-group mb-[10px]">
                <input
                  type="text"
                  class="form-control block w-full py-[9.5px] px-[12px] text-base font-normal text-gray-700 bg-white bg-clip-padding border border-light-grey rounded transition ease-in-out m-0 focus:border-primary-blue focus:outline-none"
                  id="address2"
                  placeholder="{{ ctrans('texts.address2') }}"
                  name="address2"
                  value="{{$client->address2}}">      
              </div>
              <div
                class="flex form-group flex justify-center gap-[13px] mb-[10px]"
              >
                <div class="w-full gap-x-2 md:w-1/3">
                  <div class="form-group">
                    <input
                      type="text"
                      class="form-control block w-full py-[9.5px] px-[12px] text-base font-normal text-gray-700 bg-white bg-clip-padding border border-light-grey rounded transition ease-in-out m-0 focus:border-primary-blue focus:outline-none"
                      id="city"
                      placeholder="{{ ctrans('texts.city') }}"
                      name="city"
                      value="{{$client->city}}">
                  </div>
                </div>
                <div class="w-full gap-x-2 md:w-1/3">
                  <div class="form-group">
                    <input
                      type="text"
                      class="form-control block w-full py-[9.5px] px-[12px] text-base font-normal text-gray-700 bg-white bg-clip-padding border border-light-grey rounded transition ease-in-out m-0 focus:border-primary-blue focus:outline-none"
                      id="state"
                      placeholder="{{ ctrans('texts.state') }}"
                      name="state"
                      value="{{$client->state}}">
                  </div>
                </div>
                <div class="w-full gap-x-2 md:w-1/3">
                  <div class="form-group">
                    <input
                      type="text"
                      class="form-control block w-full py-[9.5px] px-[12px] text-base font-normal text-gray-700 bg-white bg-clip-padding border border-light-grey rounded transition ease-in-out m-0 focus:border-primary-blue focus:outline-none"
                      id="postal_code"
                      placeholder="{{ ctrans('texts.postal_code') }}"
                      name="postal_code"
                      value="{{$client->postal_code}}">
                  </div>
                </div>
              </div>
              <div class="form-group mb-[10px]">
                <select name="country" id="country" class="form-select w-full py-[9.5px] px-[12px] border border-light-grey rounded transition ease-in-out m-0 focus:border-primary-blue focus:outline-none">
                    <option value="{{ $client->country->id}}" selected>{{ $client->country->iso_3166_2 }} ({{ $client->country->name }})</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->iso_3166_2 }} ({{ $country->name }})></option>
                    @endforeach
                </select>
              </div>
              <div class="mb-[10px]">
                <div id="card-element" class="border p-4 rounded text-base font-normal text-gray-700 bg-white bg-clip-padding border border-light-grey rounded focus:border-primary-blue focus:outline-none StripeElement StripeElement--empty"></div>
              </div>
              <div class="flex justify-end">
                <button
                    @isset($form) form="{{ $form }}" @endisset
                    type="{{ $type ?? 'button' }}"
                    id="{{ $id ?? 'pay-now' }}"
                    @isset($data) @foreach($data as $prop => $value) data-{{ $prop }}="{{ $value }}" @endforeach @endisset
                    class="bg-primary-blue hover:opacity-80 button button-primary bg-primary rounded-sm text-sm transition duration-300 ease-in {{ $class ?? '' }}"
                    {{ isset($disabled) && $disabled === true ? 'disabled' : '' }}>
                        <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    <span>{{ $slot ?? ctrans('texts.trial_call_to_action') }}</span>
                </button>
              </div>
              <div class="flex justify-end mt-5">
                <span class="text-[12px]"
                  >* At the end of your 14 day trial your card will be charged
                  $10/month. Cancel anytime.</span
                >
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="mt-[50px]">
        <h2 class="text-center text-[24px] mb-[50px] leading-[1.3em]">
          Skip the 14-day trial and get a discounted annual upgrade!
        </h2>
        <div class="flex flex-col gap-y-[20px] justify-center md:flex-row md:gap-x-[21px]">
          <div class="w-1/1 md:w-1/2 md:flex">
            <div
              class="w-[100%] flex flex-col border border-light-grey rounded text-center border-t-[0px]"
            >
              <div
                class="border-t-[10px] border-primary-blue rounded pt-[17px] pb-[23px] py-[22px]"
              >
                <h4 class="text-[18px] text-dark-grey uppercase">PRO Plan</h4>
                <h3
                  class="text-[40px] font-semibold leading-[1.35em] mb-[8px] text-primary-blue"
                >
                  $100<span class="font-normal text-[18px] ml-[5px] text-dark-grey"
                    >/year</span
                  >
                </h3>
                <p class="font-[16px] leading-[1.36em]">
                  Buy 10 months, get 2 free!
                </p>
              </div>
              <div class="grow flex flex-col border-t-[1px] border-light-grey pt-[21px] pb-[26px] px-[22px]">
                <h5 class="text-[14px] font-semibold leading-[1.36em] uppercase text-dark-grey mb-[21px]">Whats included</h5>
                <ul class="mb-[24px] gap-[10px] text-left list-checkmark flex justify-between md:gap-[18px]">
                  <li>Unlimited Clients & Invoicing</li>
                  <li>Remove "Created by Invoice Ninja"</li>
                  <li>API Integration with 3rd Party Apps</li>
                </ul>
                <p
                  class="mb-[30px] font-semibold text-[18px] text-center italic"
                >
                  &amp; Much More!
                </p>
                <a href="https://invoiceninja.invoicing.co/client/subscriptions/q9wdL9wejP/purchase" class="mt-[auto] bg-primary-blue hover:opacity-80 button button-primary bg-primary rounded-sm text-sm transition duration-300 ease-in"> Buy Now! </a>
              </div>
            </div>
          </div>
          <div class="w-1/1 md:w-1/2 md:flex">
            <div
              class="w-[100%] flex flex-col border border-light-grey rounded text-center"
            >
              <div
                class="border-t-[11px] border-[transparent] rounded pt-[17px] pb-[23px] py-[22px]"
              >
                <h4 class="text-[18px] text-dark-grey uppercase">Enterprise Plan</h4>
                <h3
                  class="text-[40px] font-semibold leading-[1.35em] mb-[8px] text-primary-blue"
                >
                  $140<span class="font-normal text-[18px] ml-[5px] text-dark-grey"
                    >/year</span
                  >
                </h3>
                <p class="font-[16px] leading-[1.36em]">
                  Buy 10 months, get 2 free!
                </p>
              </div>
              <div class="grow flex flex-col border-t-[1px] border-light-grey pt-[21px] pb-[26px] px-[22px]">
                <h5 class="text-[14px] font-semibold leading-[1.36em] uppercase text-dark-grey mb-[21px]">Whats included</h5>
                <ul class="mb-[24px] gap-[10px] text-left list-checkmark flex justify-between md:gap-[18px]">
                  <li>Additional Account Users</li>
                  <li>Fully Branded Client Portal</li>
                  <li>Attach 3rd Party Documents</li>
                </ul>

                <p
                  class="mb-[30px] font-semibold text-[18px] text-center italic"
                >
                  &amp; Much More!
                </p>
                <a href="https://invoiceninja.invoicing.co/client/subscriptions/LYqaQWldnj/purchase" class="mt-[auto] bg-primary-blue hover:opacity-80 button button-primary bg-primary rounded-sm text-sm transition duration-300 ease-in"> Buy Now! </a>
              </div>
            </div>
          </div>
        </div>
      </div>


@endsection

@push('footer')
<script src="https://js.stripe.com/v3/"></script>

<script type="text/javascript">

var stripe = Stripe('{{ $gateway->getPublishableKey()}}');
var client_secret = '{{ $intent->client_secret }}';

var elements = stripe.elements({
  clientSecret: client_secret,
});

var cardElement = elements.create('card', {
    value: {
        postalCode: document.querySelector('input[name=postal_code]').content,
        name: document.querySelector('input[name=name]').content
    }
});

cardElement.mount('#card-element');

const form = document.getElementById('card-form');

var e = document.getElementById("country");
var country_value = e.options[e.selectedIndex].value;

  document
      .getElementById('pay-now')
      .addEventListener('click', () => {

        let payNowButton = document.getElementById('pay-now');
        payNowButton = payNowButton;
        payNowButton.disabled = true;
        payNowButton.querySelector('svg').classList.remove('hidden');
        payNowButton.querySelector('span').classList.add('hidden');

        stripe.handleCardSetup(this.client_secret, cardElement, {
                payment_method_data: {
                      billing_details: {
                        name: document.querySelector('input[name=name]').content,
                },
              }
            })
            .then((result) => {
                if (result.error) {

                  let errors = document.getElementById('errors');
                  let payNowButton = document.getElementById('pay-now');

                  errors.textContent = '';
                  errors.textContent = result.error.message;
                  errors.hidden = false;

                  payNowButton.disabled = false;
                  payNowButton.querySelector('svg').classList.add('hidden');
                  payNowButton.querySelector('span').classList.remove('hidden');
                  return;

                }

              document.querySelector(
                  'input[name="gateway_response"]'
              ).value = JSON.stringify(result.setupIntent);

                document.getElementById('card-form').submit();
                
              });

      });

</script>
@endpush