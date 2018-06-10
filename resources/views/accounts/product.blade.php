@extends('header')

@section('content')
    @parent

    {!! Former::open($url)
            ->method($method)
            ->autocomplete('off')
            ->rules(['product_key' => 'required|max:255'])
            ->addClass('col-lg-10 col-lg-offset-1 main-form warn-on-exit') !!}

    @if ($product)
        {{ Former::populate($product) }}
        {{ Former::populateField('cost', Utils::roundSignificant($product->cost)) }}
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
        {!! Former::text('action') !!}
    </span>

    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">

            <div class="panel panel-default">
                <div class="panel-body form-padding-right">

                    {!! Former::text('product_key')->label('texts.product') !!}
                    {!! Former::textarea('notes')->rows(6) !!}

                    @include('partials/custom_fields', ['entityType' => ENTITY_PRODUCT])

                    {!! Former::text('cost') !!}

                    @if ($account->invoice_item_taxes)
                        @include('partials.tax_rates')
                    @endif

                </div>
            </div>
        </div>
    </div>

    @foreach(Module::getOrdered() as $module)
        @if(View::exists($module->alias . '::products.edit'))
        <div class="row">
            <div class="col-lg-10 col-lg-offset-1">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title in-white">
                            <i class="fa fa-{{ $module->icon }}"></i>
                            {{ $module->name }}
                        </h3>
                    </div>
                    <div class="panel-body form-padding-right">
                        @includeIf($module->alias . '::products.edit')
                    </div>
                </div>
            </div>
        </div>
        @endif
    @endforeach

    @if (Auth::user()->canCreateOrEdit(ENTITY_PRODUCT, $product))
    <center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/products'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
        @if ($product)
            {!! DropdownButton::normal(trans('texts.more_actions'))
                    ->withContents($product->present()->moreActions())
                    ->large()
                    ->dropup() !!}
        @endif
    </center>
    @endif
    {!! Former::close() !!}

    <script type="text/javascript">

        $(function() {
            $('#product_key').focus();
        });

        function submitAction(action) {
            $('#action').val(action);
            $('.main-form').submit();
        }

        function onDeleteClick() {
            sweetConfirm(function() {
                submitAction('delete');
            });
        }

    </script>

@stop
