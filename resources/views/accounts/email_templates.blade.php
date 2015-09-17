@extends('accounts.nav')

@section('head')
    @parent

    <style type="text/css">
        textarea {
            min-height: 150px !important;
        }
    </style>

@stop

@section('content')
    @parent
    @include('accounts.nav_advanced')

    {!! Former::open()->addClass('col-md-10 col-md-offset-1 warn-on-exit') !!}
    {!! Former::populateField('email_template_invoice', $invoiceEmail) !!}
    {!! Former::populateField('email_template_quote', $quoteEmail) !!}
    {!! Former::populateField('email_template_payment', $paymentEmail) !!}

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.email_templates') !!}</h3>
        </div>
        <div class="panel-body">
            <div class="row">

                <div role="tabpanel">
                    <ul class="nav nav-tabs" role="tablist" style="border: none">
                        <li role="presentation" class="active"><a href="#invoice" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.invoice_email') }}</a></li>
                        <li role="presentation"><a href="#quote" aria-controls="terms" role="tab" data-toggle="tab">{{ trans('texts.quote_email') }}</a></li>
                        <li role="presentation"><a href="#payment" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.payment_email') }}</a></li>
                    </ul>

                    <div class="tab-content">
                        <div role="tabpanel" class="tab-pane active" id="invoice">
                            <div class="panel-body">
                                <div class="col-md-6">
                                    {!! Former::textarea('email_template_invoice')->raw() !!}
                                </div>
                                <div class="col-md-6" id="invoice_preview"></div>
                            </div>
                        </div>

                        <div role="tabpanel" class="tab-pane" id="quote">
                            <div class="panel-body">
                                <div class="col-md-6">
                                    {!! Former::textarea('email_template_quote')->raw() !!}
                                </div>
                                <div class="col-md-6" id="quote_preview"></div>
                            </div>
                        </div>


                        <div role="tabpanel" class="tab-pane" id="payment">
                            <div class="panel-body">
                                <div class="col-md-6">
                                    {!! Former::textarea('email_template_payment')->raw() !!}
                                </div>
                                <div class="col-md-6" id="payment_preview"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <p>&nbsp;</p>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.reminder_emails') !!}</h3>
        </div>
        <div class="panel-body">
            <div class="row">

                <div role="tabpanel">
                    <ul class="nav nav-tabs" role="tablist" style="border: none">
                        <li role="presentation" class="active"><a href="#invoice" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.invoice_email') }}</a></li>
                        <li role="presentation"><a href="#quote" aria-controls="terms" role="tab" data-toggle="tab">{{ trans('texts.quote_email') }}</a></li>
                        <li role="presentation"><a href="#payment" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.payment_email') }}</a></li>
                    </ul>

                    <div class="tab-content">
                        <div role="tabpanel" class="tab-pane active" id="invoice">
                            <div class="panel-body">
                                <div class="col-md-6">
                                    {!! Former::textarea('email_template_invoice')->raw() !!}
                                </div>
                                <div class="col-md-6" id="invoice_preview"></div>
                            </div>
                        </div>

                        <div role="tabpanel" class="tab-pane" id="quote">
                            <div class="panel-body">
                                <div class="col-md-6">
                                    {!! Former::textarea('email_template_quote')->raw() !!}
                                </div>
                                <div class="col-md-6" id="quote_preview"></div>
                            </div>
                        </div>


                        <div role="tabpanel" class="tab-pane" id="payment">
                            <div class="panel-body">
                                <div class="col-md-6">
                                    {!! Former::textarea('email_template_payment')->raw() !!}
                                </div>
                                <div class="col-md-6" id="payment_preview"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>



    @if (Auth::user()->isPro())
        <center>
            {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
        </center>
    @else
        <script>
            $(function() {
                $('form.warn-on-exit input').prop('disabled', true);
            });
        </script>
    @endif

    {!! Former::close() !!}

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

            keys = ['footer', 'account', 'client', 'amount', 'link', 'contact'];
            vals = [{!! json_encode($emailFooter) !!}, '{!! Auth::user()->account->getDisplayName() !!}', 'Client Name', formatMoney(100), '{!! SITE_URL . '/view/...' !!}', 'Contact Name'];

            // Add any available payment method links
            @foreach (\App\Models\Gateway::getPaymentTypeLinks() as $type)
                {!! "keys.push('" . $type.'_link' . "');" !!}
                {!! "vals.push('" . URL::to("/payment/xxxxxx/{$type}") . "');" !!}
            @endforeach

            for (var i=0; i<keys.length; i++) {
                var regExp = new RegExp('\\$'+keys[i], 'g');
                str = str.replace(regExp, vals[i]);
            }

            return str;
        }

    </script>

@stop
