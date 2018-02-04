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
    .icon-select {
        font-family: sans-serif, 'FontAwesome';
    }
    </style>

@stop

@section('content')

    {!! Former::open($url)
            ->method($method)
            ->id('mainForm')
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
                                ->options($icons) !!}
                    </div>
                    <div class="col-md-6">
                        {!! Former::textarea('private_notes')
                                ->style('height:98px') !!}
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

    var categories = {!! $categories !!};
    var categoryMap = {};

    function onSaveClick() {
        $('#html').val(grapesjsEditor.getHtml());
        $('#css').val(grapesjsEditor.getCss());
        $('#mainForm').submit();
    }

    $(function() {
        var categoryId = {{ $categoryPublicId ?: 0 }};
        var $proposal_categorySelect = $('select#proposal_category_id');
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
