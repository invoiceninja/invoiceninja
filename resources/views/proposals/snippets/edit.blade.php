@extends('header')

@section('head')
    @parent

    @include('proposals.grapesjs_header')

    <style>
    .icon-select {
        font-family: FontAwesome, sans-serif;
    }
    </style>

@stop

@section('content')

    {!! Former::open($url)
            ->method($method)
            ->onsubmit('return onFormSubmit(event)')
            ->addClass('warn-on-exit')
            ->rules([
                'name' => 'required',
            ]) !!}

    @if ($snippet)
        {!! Former::populate($snippet) !!}
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
                        {!! Former::select('proposal_category_id')->addOption('', '')
                                ->label(trans('texts.category'))
                                ->addGroupClass('category-select') !!}
                        {!! Former::select('icon')
                                ->addGroupClass('icon-select')
                                ->addOption('', '')
                                ->options($icons) !!}
                    </div>
                    <div class="col-md-6">
                        {!! Former::textarea('private_notes')
                                ->style('height:160px') !!}
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

        {!! Button::success(trans("texts.save"))
                ->submit()
                ->appendIcon(Icon::create('floppy-disk')) !!}
    </center>

    {!! Former::close() !!}

    <div id="gjs"></div>

    <script type="text/javascript">

    var categories = {!! $categories !!};
    var categoryMap = {};

    function onFormSubmit() {
        $('#html').val(grapesjsEditor.getHtml());
        $('#css').val(grapesjsEditor.getCss());

        return true;
    }

    $(function() {
        var categoryId = {{ $categoryPublicId ?: 0 }};
        var $proposal_categorySelect = $('select#proposal_category_id');
        @if (Auth::user()->can('create', ENTITY_PROPOSAL_CATEGORY))
            $proposal_categorySelect.append(new Option("{{ trans('texts.create_proposal_category') }}: $name", '-1'));
        @endif
        for (var i = 0; i < categories.length; i++) {
            var category = categories[i];
            categoryMap[category.public_id] = category;
            $proposal_categorySelect.append(new Option(category.name, category.public_id));
        }
        @include('partials/entity_combobox', ['entityType' => ENTITY_PROPOSAL_CATEGORY])
        if (categoryId) {
            var category = categoryMap[categoryId];
            setComboboxValue($('.category-select'), category.public_id, category.name);
        }

        $('#icon').combobox();
    })

</script>

@include('proposals.grapesjs', ['entity' => $snippet])

@stop
