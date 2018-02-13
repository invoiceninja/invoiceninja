@extends('header')

@section('head')
    @parent

    @include('proposals.grapesjs_header')

@stop

@section('content')

    {!! Former::open($url)
            ->method($method)
            ->onsubmit('return onFormSubmit(event)')
            ->addClass('warn-on-exit')
            ->rules([
                'name' => 'required',
            ]) !!}

    @if ($template)
        {!! Former::populate($template) !!}
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
        {!! Former::text('html') !!}
        {!! Former::text('css') !!}
    </span>

    <div class="row">
		<div class="col-lg-12">
            <div class="panel panel-default">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        {!! Former::text('name') !!}
                    </div>
                    <div class="col-md-6">
                        {!! Former::textarea('private_notes') !!}
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <center class="buttons">

        @if (count($templateOptions))
            {!! Former::select()
                    ->style('display:inline;width:170px;background-color:white !important')
                    ->placeholder(trans('texts.load_template'))
                    ->onchange('onTemplateSelectChange()')
                    ->addClass('template-select')
                    ->options($templateOptions)
                    ->raw() !!}
        @endif

        @include('proposals.grapesjs_help')

        {!! Button::normal(trans('texts.cancel'))
                ->appendIcon(Icon::create('remove-circle'))
                ->asLinkTo(HTMLUtils::previousUrl('/proposals')) !!}

        {!! Button::success(trans('texts.save'))
                ->submit()
                ->appendIcon(Icon::create('floppy-disk')) !!}

        @if ($template)
            {!! Button::primary(trans('texts.new_proposal'))
                    ->appendIcon(Icon::create('plus-sign'))
                    ->asLinkTo(url('/proposals/create/0/' . $template->public_id)) !!}
        @endif

    </center>

    {!! Former::close() !!}

    <div id="gjs"></div>

    <script type="text/javascript">
    var customTemplates = {!! $customTemplates !!};
    var customTemplateMap = {};

    var defaultTemplates = {!! $defaultTemplates !!};
    var defaultTemplateMap = {};

    function onFormSubmit() {
        $('#html').val(grapesjsEditor.getHtml());
        $('#css').val(grapesjsEditor.getCss());

        return true;
    }

    function onTemplateSelectChange() {
        var templateId = $('.template-select').val();
        var group = $('.template-select :selected').parent().attr('label');

        if (group == "{{ trans('texts.default') }}") {
            var template = defaultTemplateMap[templateId];
        } else {
            var template = customTemplateMap[templateId];
        }

        grapesjsEditor.CssComposer.getAll().reset();
        grapesjsEditor.setComponents(template.html);
        grapesjsEditor.setStyle(template.css);

        $('.template-select').val(null).blur();
    }

    $(function() {
        for (var i=0; i<customTemplates.length; i++) {
            var template = customTemplates[i];
            customTemplateMap[template.public_id] = template;
        }
        for (var i=0; i<defaultTemplates.length; i++) {
            var template = defaultTemplates[i];
            defaultTemplateMap[template.public_id] = template;
        }
    })

</script>

@include('proposals.grapesjs', ['entity' => $template])

@stop
