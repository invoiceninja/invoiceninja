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

    {!! Former::vertical_open()->addClass('col-md-10 col-md-offset-1 warn-on-exit') !!}
    {!! Former::populate($account) !!}

    @foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, REMINDER1, REMINDER2, REMINDER3] as $type)
        @foreach (['subject', 'template'] as $field)
            {!! Former::populateField("email_{$field}_{$type}", $templates[$type][$field]) !!}
        @endforeach
    @endforeach

    {!! Former::populateField("enable_reminder1", intval($account->enable_reminder1)) !!}
    {!! Former::populateField("enable_reminder2", intval($account->enable_reminder2)) !!}
    {!! Former::populateField("enable_reminder3", intval($account->enable_reminder3)) !!}    

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
                        @include('accounts.template', ['field' => 'invoice', 'active' => true])
                        @include('accounts.template', ['field' => 'quote'])
                        @include('accounts.template', ['field' => 'payment'])
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
                        <li role="presentation" class="active"><a href="#reminder1" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.first_reminder') }}</a></li>
                        <li role="presentation"><a href="#reminder2" aria-controls="terms" role="tab" data-toggle="tab">{{ trans('texts.second_reminder') }}</a></li>
                        <li role="presentation"><a href="#reminder3" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.third_reminder') }}</a></li>
                    </ul>
                    <div class="tab-content">
                        @include('accounts.template', ['field' => 'reminder1', 'isReminder' => true, 'active' => true])
                        @include('accounts.template', ['field' => 'reminder2', 'isReminder' => true])
                        @include('accounts.template', ['field' => 'reminder3', 'isReminder' => true])
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

        var entityTypes = ['invoice', 'quote', 'payment', 'reminder1', 'reminder2', 'reminder3'];
        var stringTypes = ['subject', 'template'];
        var templates = {!! json_encode($defaultTemplates) !!};

        function refreshPreview() {
            for (var i=0; i<entityTypes.length; i++) {
                var entityType = entityTypes[i];
                for (var j=0; j<stringTypes.length; j++) {
                    var stringType = stringTypes[j];
                    var idName = '#email_' + stringType + '_' + entityType;
                    var value = $(idName).val();
                    var previewName = '#' + entityType + '_' + stringType + '_preview';
                    $(previewName).html(processVariables(value));
                }
            }            
        }

        $(function() {
            for (var i=0; i<entityTypes.length; i++) {
                var entityType = entityTypes[i];
                for (var j=0; j<stringTypes.length; j++) {
                    var stringType = stringTypes[j];
                    var idName = '#email_' + stringType + '_' + entityType;
                    $(idName).keyup(refreshPreview);
                }
            }

            for (var i=1; i<=3; i++) {
                $('#enable_reminder' + i).bind('click', {id: i}, function(event) {
                    enableReminder(event.data.id)
                });
                enableReminder(i);
            }

            refreshPreview();
        });

        function enableReminder(id) {            
            var checked = $('#enable_reminder' + id).is(':checked');
            $('.enable-reminder' + id).attr('disabled', !checked)
        }

        function processVariables(str) {
            if (!str) {
                return '';
            }

            keys = [
                'footer', 
                'account', 
                'client', 
                'amount', 
                'link', 
                'contact', 
                'invoice', 
                'quote'
            ];

            vals = [
                {!! json_encode($emailFooter) !!}, 
                "{{ Auth::user()->account->getDisplayName() }}", 
                "Client Name", 
                formatMoney(100), 
                "{{ Auth::user()->account->getSiteUrl() . '...' }}", 
                "Contact Name", 
                "0001", 
                "0001"
            ];

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

        function resetText(section, field) {
            if (confirm('{!! trans("texts.are_you_sure") !!}')) {
                var fieldName = 'email_' + section + '_' + field;
                var value = templates[field][section];
                $('#' + fieldName).val(value);
                refreshPreview();
            }

            return false;
        }

    </script>

@stop
