@extends('header')

@section('head')
    @parent

    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

    <style type="text/css">
        .iframe_url {
            display: none;
        }
    </style>
@stop

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_EMAIL_SETTINGS, 'advanced' => true])

    {!! Former::open()->rules([
            'bcc_email' => 'email',
            'reply_to_email' => 'email',
        ])->addClass('warn-on-exit') !!}

    {{ Former::populate($account) }}
    {{ Former::populateField('pdf_email_attachment', intval($account->pdf_email_attachment)) }}
    {{ Former::populateField('document_email_attachment', intval($account->document_email_attachment)) }}
    {{ Former::populateField('enable_email_markup', intval($account->enable_email_markup)) }}
    {{ Former::populateField('bcc_email', $account->account_email_settings->bcc_email) }}
    {{ Former::populateField('reply_to_email', $account->account_email_settings->reply_to_email) }}

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.email_settings') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            {!! Former::text('reply_to_email')
                    ->placeholder(Auth::user()->registered ? Auth::user()->email : ' ')
                    ->help('reply_to_email_help') !!}

            {!! Former::text('bcc_email')
                    ->help('bcc_email_help') !!}

            &nbsp;

            {!! Former::checkbox('pdf_email_attachment')
                    ->text(trans('texts.enable'))
                    ->value(1)
                    ->help( ! Utils::isNinja() ? (config('pdf.phantomjs.bin_path') ? (config('pdf.phantomjs.cloud_key') ? 'phantomjs_local_and_cloud' : 'phantomjs_local') : trans('texts.phantomjs_help', [
                        'link_phantom' => link_to('https://phantomjscloud.com/', 'phantomjscloud.com', ['target' => '_blank']),
                        'link_docs' => link_to('http://docs.invoiceninja.com/en/latest/configure.html#phantomjs', 'PhantomJS', ['target' => '_blank'])
                    ])) : false) !!}

            {!! Former::checkbox('document_email_attachment')
                    ->text(trans('texts.enable'))
                    ->value(1) !!}

            &nbsp;

            {{-- Former::select('recurring_hour')->options($recurringHours) --}}

        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.email_design') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            {!! Former::select('email_design_id')
                        ->appendIcon('question-sign')
                        ->addGroupClass('email_design_id')
                        ->addOption(trans('texts.plain'), EMAIL_DESIGN_PLAIN)
                        ->addOption(trans('texts.light'), EMAIL_DESIGN_LIGHT)
                        ->addOption(trans('texts.dark'), EMAIL_DESIGN_DARK)
                        ->help(trans('texts.email_design_help')) !!}

            &nbsp;

            @if (Utils::isNinja())
                {!! Former::checkbox('enable_email_markup')
                        ->text(trans('texts.enable') .
                            '<a href="'.EMAIL_MARKUP_URL.'" target="_blank" title="'.trans('texts.learn_more').'">' . Icon::create('question-sign') . '</a> ')
                        ->help(trans('texts.enable_email_markup_help'))
                        ->value(1) !!}
            @endif
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.signature') !!}</h3>
        </div>
        <div class="panel-body">
            {!! Former::textarea('email_footer')->style('display:none')->raw() !!}
            <div id="signatureEditor" class="form-control" style="min-height:160px" onclick="focusEditor()"></div>
            @include('partials/quill_toolbar', ['name' => 'signature'])
        </div>
    </div>

    @if (Auth::user()->hasFeature(FEATURE_CUSTOM_EMAILS))
        <center>
            {!! Button::success(trans('texts.save'))->large()->submit()->appendIcon(Icon::create('floppy-disk')) !!}
        </center>
    @endif

    <div class="modal fade" id="designHelpModal" tabindex="-1" role="dialog" aria-labelledby="designHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="min-width:150px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="designHelpModalLabel">{{ trans('texts.email_designs') }}</h4>
                </div>

                <div class="container" style="width: 100%; padding-bottom: 0px !important">
                <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row" style="text-align:center">
                        <div class="col-md-4">
                            <h4>{{ trans('texts.plain') }}</h4><br/>
                            <img src="{{ asset('images/emails/plain.png') }}" class="img-responsive"/>
                        </div>
                        <div class="col-md-4">
                            <h4>{{ trans('texts.light') }}</h4><br/>
                            <img src="{{ asset('images/emails/light.png') }}" class="img-responsive"/>
                        </div>
                        <div class="col-md-4">
                            <h4>{{ trans('texts.dark') }}</h4><br/>
                            <img src="{{ asset('images/emails/dark.png') }}" class="img-responsive"/>
                        </div>
                    </div>
                </div>
                </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
                </div>

            </div>
        </div>
    </div>

    {!! Former::close() !!}

    <script type="text/javascript">

        var editor = false;
        $(function() {
            editor = new Quill('#signatureEditor', {
                modules: {
                    'toolbar': { container: '#signatureToolbar' },
                    'link-tooltip': true
                },
                theme: 'snow'
            });
            editor.setHTML($('#email_footer').val());
            editor.on('text-change', function(delta, source) {
                if (source == 'api') {
                    return;
                }
                var html = editor.getHTML();
                $('#email_footer').val(html);
                NINJA.formIsChanged = true;
            });
        });

        function focusEditor() {
            editor.focus();
        }

        $('.email_design_id .input-group-addon').click(function() {
            $('#designHelpModal').modal('show');
        });

    </script>

@stop
