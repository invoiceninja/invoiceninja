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

                        <!--
                        {!! Former::select('proposal_template_id')->addOption('', '')
                                ->label(trans('texts.template'))
                                ->addGroupClass('template-select') !!}
                        -->
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
    var templates = {!! $templates !!};
    var templateMap = {};

    function onFormSubmit() {
        $('#html').val(grapesjsEditor.getHtml());
        $('#css').val(grapesjsEditor.getCss());

        return true;
    }

    $(function() {
        /*
        var $proposal_templateSelect = $('select#template_id');
        for (var i = 0; i < templates.length; i++) {
            var template = templates[i];
            templateMap[template.public_id] = template;
            $templateSelect.append(new Option(template.name, template.public_id));
        }
        @include('partials/entity_combobox', ['entityType' => ENTITY_PROPOSAL_TEMPLATE])
        */
    })

</script>

@include('proposals.grapesjs', ['entity' => $template])

@stop
