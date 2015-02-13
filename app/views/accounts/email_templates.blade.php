@extends('accounts.nav')

@section('head')
    @parent

    <style type="text/css">
        textarea {
            min-height: 100px !important;
        }
    </style>

@stop

@section('content')
    @parent
    @include('accounts.nav_advanced')

    {{ Former::open()->addClass('col-md-8 col-md-offset-2 warn-on-exit') }}
    {{ Former::populateField('email_template_invoice', $invoiceEmail) }}
    {{ Former::populateField('email_template_quote', $quoteEmail) }}
    {{ Former::populateField('email_template_payment', $paymentEmail) }}

    {{ Former::legend('invoice_email') }}
    <div class="row">
        <div class="col-md-7">
            {{ Former::textarea('email_template_invoice')->raw() }}
        </div>
        <div class="col-md-5" id="invoice_preview"></div>
    </div>

    <p>&nbsp;</p>

    {{ Former::legend('quote_email') }}
    <div class="row">
        <div class="col-md-7">
            {{ Former::textarea('email_template_quote')->raw() }}
        </div>
        <div class="col-md-5" id="quote_preview"></div>
    </div>

    <p>&nbsp;</p>

    {{ Former::legend('payment_email') }}
    <div class="row">
        <div class="col-md-7">
            {{ Former::textarea('email_template_payment')->raw() }}
        </div>
        <div class="col-md-5" id="payment_preview"></div>
    </div>

    <p>&nbsp;</p>

    @if (Auth::user()->isPro())
        {{ Former::actions( 
            Button::lg_success_submit(trans('texts.save'))->append_with_icon('floppy-disk')
        ) }}
    @else
        <script>
            $(function() {
                $('form.warn-on-exit input').prop('disabled', true);
            });
        </script>
    @endif

    {{ Former::close() }}

    <script type="text/javascript">

        $(function() {
            $('#email_template_invoice').keyup(refreshInvoice);
            $('#email_template_quote').keyup(refreshQuote);
            $('#email_template_payment').keyup(refreshPayment);

            refreshInvoice();
            refreshQuote();
            refreshPayment();
        });

        function refreshInvoice() {
            $('#invoice_preview').html(processVariables($('#email_template_invoice').val()));
        }

        function refreshQuote() {
            $('#quote_preview').html(processVariables($('#email_template_quote').val()));
        }

        function refreshPayment() {
            $('#payment_preview').html(processVariables($('#email_template_payment').val()));
        }

        function processVariables(str) {
            if (!str) {
                return '';
            }

            keys = ['footer', 'account', 'client', 'amount', 'link'];
            vals = [{{ json_encode($emailFooter) }}, '{{ Auth::user()->account->getDisplayName() }}', 'Client Name', formatMoney(100), '{{ NINJA_WEB_URL }}']

            for (var i=0; i<keys.length; i++) {
                var regExp = new RegExp('\\$'+keys[i], 'g');
                str = str.replace(regExp, vals[i]);
            }

            return str;
        }

    </script>

@stop
