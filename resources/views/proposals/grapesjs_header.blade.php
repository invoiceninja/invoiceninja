<script src="{{ asset('js/grapesjs.min.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
<script src="{{ asset('js/grapesjs-blocks-basic.min.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
<script src="{{ asset('js/grapesjs-preset-newsletter.min.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
<link href="{{ asset('css/grapesjs.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

<style>

.gjs-four-color,
.gjs-four-color-h:hover {
    color: #3b97ff !important;
}

.gjs-rte-actionbar,
.gjs-block-label,
.gjs-block-categories {
    font-size: 12px !important;
}

.gjs-mdl-title {
    font-size: 1em !important;
}
.gjs-mdl-dialog,
.gjs-toolbar-item {
    font-size: 2em !important;
}

#gjs-clm-status-c {
    display: none !important;
}

/* Workaround for https://github.com/artf/grapesjs/issues/596 */
.sp-container.sp-light {
    left: 300px !important;
}

</style>
