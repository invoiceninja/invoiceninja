@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/grapesjs.min.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
    <link href="{{ asset('css/grapesjs.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

    <style>
    .gjs-four-color {
        color: white !important;
    }
    .gjs-block.fa {
        font-size: 4em !important;
    }
    </style>

@stop

@section('content')

    {!! Former::open($url)
            ->method($method)
            ->id('mainForm')
            ->rules([
                'quote_id' => 'required',
				'template_id' => 'required',
            ]) !!}

    <div class="row">
		<div class="col-lg-12">
            <div class="panel panel-default">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        {!! Former::select('quote_id')->addOption('', '')
                                ->label(trans('texts.quote'))
                                ->addGroupClass('quote-select') !!}

                    </div>
                    <div class="col-md-6">
                        {!! Former::select('template_id')->addOption('', '')
                                ->label(trans('texts.template'))
                                ->addGroupClass('template-select') !!}

                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <center class="buttons">
        {!! Button::normal(trans('texts.cancel'))
                ->appendIcon(Icon::create('remove-circle'))
                ->asLinkTo(HTMLUtils::previousUrl('/proposals')) !!}

        {!! Button::success(trans("texts.save"))
                ->withAttributes(array('id' => 'saveButton', 'onclick' => 'onSaveClick()'))
                ->appendIcon(Icon::create('floppy-disk')) !!}
    </center>

    {!! Former::close() !!}

    <div id="gjs"></div>

    <script type="text/javascript">
    var templates = {!! $templates !!};
    var templateMap = {};

    $(function() {
        var $proposal_templateSelect = $('select#template_id');
        for (var i = 0; i < templates.length; i++) {
            var template = templates[i];
            templateMap[template.public_id] = template;
            $templateSelect.append(new Option(template.name, template.public_id));
        }
        @include('partials/entity_combobox', ['entityType' => ENTITY_PROPOSAL_TEMPLATE])

        var editor = grapesjs.init({
            container : '#gjs',
            components: '',
            style: '',
            showDevices: false,
            plugins: ['gjs-preset-newsletter'],
            //plugins: ['gjs-blocks-basic'],
            storageManager: {type: 'none'},
            panels: {
                Xdefaults  : [{
                    id      : 'commands',
                    buttons : [{
                        id          : 'smile',
                        className   : 'fa fa-smile-o',
                        attributes  : { title: 'Smile' }
                    }],
                }],
            }
        });

        /*
        var blockManager = editor.BlockManager;
        blockManager.add('h1-block', {
        label: 'Heading',
        category: 'Basic',
        content: '<h1>Put your title here</h1>',
        attributes: {
        title: 'Insert h1 block',
        class:'fa fa-smile-o'
            }
        });
        */
})

</script>

@stop
