@extends('header')

@section('content')
    @parent

    {!! Former::open($url)
            ->method($method)
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

                    @if ($account->hasFeature(FEATURE_INVOICE_SETTINGS))
                        @if ($account->custom_invoice_item_label1)
                            {!! Former::text('custom_value1')->label(e($account->custom_invoice_item_label1)) !!}
                        @endif
                        @if ($account->custom_invoice_item_label2)
                            {!! Former::text('custom_value2')->label(e($account->custom_invoice_item_label2)) !!}
                        @endif
                    @endif

                    {!! Former::text('cost') !!}

                    @if ($account->invoice_item_taxes)
                        @include('partials.tax_rates')
                    @endif

                </div>
            </div>
        </div>
    </div>
    
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
