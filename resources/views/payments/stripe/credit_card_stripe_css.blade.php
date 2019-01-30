<style>
    .ninja.stripe {
        background-color: #fff;
    }

    .ninja.stripe * {
        font-family: Source Code Pro, Consolas, Menlo, monospace;
        font-size: 16px;
        font-weight: 500;
    }

    .ninja.stripe .row {
        display: -ms-flexbox;
        display: flex;
        margin: 0 5px 10px;
    }

    .ninja.stripe .field {
        position: relative;
        width: 100%;
        height: 50px;
        margin: 0 10px;
    }

    .ninja.stripe .field.half-width {
        width: 50%;
    }

    .ninja.stripe .field.quarter-width {
        width: calc(25% - 10px);
    }

    .ninja.stripe .baseline {
        position: absolute;
        width: 100%;
        height: 1px;
        left: 0;
        bottom: 0;
        background-color: #cfd7df;
        transition: background-color 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe label {
        position: absolute;
        width: 100%;
        left: 0;
        bottom: 8px;
        color: #cfd7df;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        transform-origin: 0 50%;
        cursor: text;
        transition-property: color, transform;
        transition-duration: 0.3s;
        transition-timing-function: cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe .input {
        position: absolute;
        width: 100%;
        left: 0;
        bottom: 0;
        padding-bottom: 7px;
        color: #32325d;
        background-color: transparent;
    }

    .ninja.stripe .input::-webkit-input-placeholder {
        color: transparent;
        transition: color 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe .input::-moz-placeholder {
        color: transparent;
        transition: color 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe .input:-ms-input-placeholder {
        color: transparent;
        transition: color 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe .input.StripeElement {
        opacity: 0;
        transition: opacity 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        will-change: opacity;
    }

    .ninja.stripe .input.focused,
    .ninja.stripe .input:not(.empty) {
        opacity: 1;
    }

    .ninja.stripe .input.focused::-webkit-input-placeholder,
    .ninja.stripe .input:not(.empty)::-webkit-input-placeholder {
        color: #cfd7df;
    }

    .ninja.stripe .input.focused::-moz-placeholder,
    .ninja.stripe .input:not(.empty)::-moz-placeholder {
        color: #cfd7df;
    }

    .ninja.stripe .input.focused:-ms-input-placeholder,
    .ninja.stripe .input:not(.empty):-ms-input-placeholder {
        color: #cfd7df;
    }

    .ninja.stripe .input.focused + label,
    .ninja.stripe .input:not(.empty) + label {
        color: #aab7c4;
        transform: scale(0.85) translateY(-25px);
        cursor: default;
    }

    .ninja.stripe .input.focused + label {
        color: #24b47e;
    }

    .ninja.stripe .input.invalid + label {
        color: #ffa27b;
    }

    .ninja.stripe .input.focused + label + .baseline {
        background-color: #24b47e;
    }

    .ninja.stripe .input.focused.invalid + label + .baseline {
        background-color: #e25950;
    }

    .ninja.stripe input, .ninja.stripe button {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        outline: none;
        border-style: none;
    }

    .ninja.stripe input:-webkit-autofill {
        -webkit-text-fill-color: #e39f48;
        transition: background-color 100000000s;
        -webkit-animation: 1ms void-animation-out;
    }

    .ninja.stripe .StripeElement--webkit-autofill {
        background: transparent !important;
    }

    .ninja.stripe input, .ninja.stripe button {
        -webkit-animation: 1ms void-animation-out;
    }

    .ninja.stripe button {
        display: block;
        width: calc(100% - 30px);
        height: 40px;
        margin: 40px 15px 0;
        background-color: #24b47e;
        border-radius: 4px;
        color: #fff;
        text-transform: uppercase;
        font-weight: 600;
        cursor: pointer;
    }

    .ninja.stripe input:active {
        background-color: #159570;
    }

    .ninja.stripe .error svg {
        margin-top: 0 !important;
    }

    .ninja.stripe .error svg .base {
        fill: #e25950;
    }

    .ninja.stripe .error svg .glyph {
        fill: #fff;
    }

    .ninja.stripe .error .message {
        color: #e25950;
    }

    .ninja.stripe .success .icon .border {
        stroke: #abe9d2;
    }

    .ninja.stripe .success .icon .checkmark {
        stroke: #24b47e;
    }

    .ninja.stripe .success .title {
        color: #32325d;
        font-size: 16px !important;
    }

    .ninja.stripe .success .message {
        color: #8898aa;
        font-size: 13px !important;
    }

    .ninja.stripe .success .reset path {
        fill: #24b47e;
    }
</style>