@extends('header')

@section('head')
    @parent

    @include('money_script')
    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

    <style type="text/css">
        textarea {
            min-height: 150px !important;
        }
    </style>

    <script type="text/javascript">
        var editors = [];
    </script>

@stop

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_TEMPLATES_AND_REMINDERS, 'advanced' => true])


    {!! Former::vertical_open()->addClass('warn-on-exit') !!}

    @foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, REMINDER1, REMINDER2, REMINDER3] as $type)
        @foreach (['subject', 'template'] as $field)
            {{ Former::populateField("email_{$field}_{$type}", $templates[$type][$field]) }}
        @endforeach
    @endforeach

    @foreach ([REMINDER1, REMINDER2, REMINDER3] as $type)
        @foreach (['enable', 'num_days', 'direction', 'field'] as $field)
            {{ Former::populateField("{$field}_{$type}", $account->{"{$field}_{$type}"}) }}
        @endforeach
    @endforeach

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


    <div class="modal fade" id="templatePreviewModal" tabindex="-1" role="dialog" aria-labelledby="templatePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="width:800px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="templatePreviewModalLabel">{{ trans('texts.preview') }}</h4>
                </div>

                <div class="container" style="width: 100%; padding-bottom: 0px !important">
                <div class="panel panel-default">
                <div class="panel-body">
                    <iframe id="server-preview" style="background-color:#FFFFFF" frameborder="1" width="100%" height="500px"/></iframe>
                </div>
                </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rawModal" tabindex="-1" role="dialog" aria-labelledby="rawModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="width:800px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="rawModalLabel">{{ trans('texts.raw_html') }}</h4>
                </div>

                <div class="container" style="width: 100%; padding-bottom: 0px !important">
                <div class="panel panel-default">
                <div class="modal-body">
                    <textarea id="raw-textarea" rows="20" style="width:100%"></textarea>
                </div>
                </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }}</button>
                    <button type="button" onclick="updateRaw()" class="btn btn-success" data-dismiss="modal">{{ trans('texts.update') }}</button>
                </div>
            </div>
        </div>
    </div>

    @if (Auth::user()->hasFeature(FEATURE_EMAIL_TEMPLATES_REMINDERS))
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
        var account = {!! Auth::user()->account !!};

        function refreshPreview() {
            for (var i=0; i<entityTypes.length; i++) {
                var entityType = entityTypes[i];
                for (var j=0; j<stringTypes.length; j++) {
                    var stringType = stringTypes[j];
                    var idName = '#email_' + stringType + '_' + entityType;
                    var value = $(idName).val();
                    var previewName = '#' + entityType + '_' + stringType + '_preview';
                    $(previewName).html(renderEmailTemplate(value));
                }
            }
        }

        function serverPreview(field) {
            $('#templatePreviewModal').modal('show');
            var template = $('#email_template_' + field).val();
            var url = '{{ URL::to('settings/email_preview') }}?template=' + template;
            $('#server-preview').attr('src', url).load(function() {
                // disable links in the preview
                $('iframe').contents().find('a').each(function(index) {
                    $(this).on('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                    });
                });
            });
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

            $('.show-when-ready').show();

            refreshPreview();
        });

        function enableReminder(id) {
            var checked = $('#enable_reminder' + id).is(':checked');
            $('.enable-reminder' + id).attr('disabled', !checked)
        }

        function setDirectionShown(field) {
            var val = $('#field_' + field).val();
            if (val == {{ REMINDER_FIELD_INVOICE_DATE }}) {
                $('#days_after_' + field).show();
                $('#direction_' + field).hide();
            } else {
                $('#days_after_' + field).hide();
                $('#direction_' + field).show();
            }
        }

        function resetText(section, field) {
            sweetConfirm(function() {
                var fieldName = 'email_' + section + '_' + field;
                var value = templates[field][section];
                $('#' + fieldName).val(value);
                if (section == 'template') {
                    editors[field].setHTML(value);
                }
                refreshPreview();
            });
        }

        function showRaw(field) {
            window.rawHtmlField = field;
            var template = $('#email_template_' + field).val();
            $('#raw-textarea').val(formatXml(template));
            $('#rawModal').modal('show');
        }

        function updateRaw() {
            var value = $('#raw-textarea').val();
            var field = window.rawHtmlField;
            editors[field].setHTML(value);
            value = editors[field].getHTML();
            var fieldName = 'email_template_' + field;
            $('#' + fieldName).val(value);
            refreshPreview();
        }

        // https://gist.github.com/sente/1083506
        function formatXml(xml) {
            var formatted = '';
            var reg = /(>)(<)(\/*)/g;
            xml = xml.replace(reg, '$1\r\n$2$3');
            var pad = 0;
            jQuery.each(xml.split('\r\n'), function(index, node) {
                var indent = 0;
                if (node.match( /.+<\/\w[^>]*>$/ )) {
                    indent = 0;
                } else if (node.match( /^<\/\w/ )) {
                    if (pad != 0) {
                        pad -= 1;
                    }
                } else if (node.match( /^<\w[^>]*[^\/]>.*$/ )) {
                    indent = 1;
                } else {
                    indent = 0;
                }

                var padding = '';
                for (var i = 0; i < pad; i++) {
                    padding += '  ';
                }

                formatted += padding + node + '\r\n';
                pad += indent;
            });

            return formatted;
        }

    </script>

    @include('partials.email_templates')

@stop
