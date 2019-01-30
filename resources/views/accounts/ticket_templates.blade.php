@extends('header')

@section('content')
    @parent
    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

    <style type="text/css">
        textarea {
            min-height: 150px !important;
        }
    </style>

    {!! Former::open($url)
            ->method($method)
            ->addClass('warn-on-exit')
            ->autocomplete('on')
            ->rules([
            'name' => 'required',
            'description' => 'required',
        ])
    !!}

    {{ Former::populate($ticket_templates) }}

        <div style="display:none">
            {!! Former::text('public_id') !!}
        </div>

    @include('accounts.nav', ['selected' => ACCOUNT_TICKETS])

    <div class="row">
        <div class="col-md-12">

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.add_template') !!}</h3>
                </div>
                <div class="panel-body form-padding-right">

                    {{trans('texts.name')}}
                    {!! Former::small_text('name')
                             ->label('')->style('width:100%;')
                    !!}

                    {{ trans('texts.description') }}

                    {!! Former::textarea('description')->label(trans('texts.description'))->style('display:none')->raw() !!}

                    <div id="descriptionEditor" class="form-control" style="min-height:160px" onclick="focusEditor()"></div>

                    <div class="pull-left">
                        @include('partials/quill_toolbar', ['name' => 'description'])
                    </div>

                    <div class="pull-right" style="padding-top:13px;text-align:right">
                        {!! Button::normal(trans('texts.raw'))->withAttributes(['onclick' => 'showRaw()'])->small() !!}
                        {!! Button::primary(trans('texts.preview'))->withAttributes(['onclick' => 'serverPreview()', 'style' => 'display:none'])->small() !!}
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

    <center>
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
    </center>

    {!! Former::close() !!}

    <script type="text/javascript">

        var editor = false;
        $(function() {
            editor = new Quill('#descriptionEditor', {
                modules: {
                    'toolbar': { container: '#descriptionToolbar' },
                    'link-tooltip': true
                },
                theme: 'snow'
            });
            editor.setHTML($('#description').val());
            editor.on('text-change', function(delta, source) {
                if (source == 'api') {
                    return;
                }
                var html = editor.getHTML();
                $('#description').val(html);
                NINJA.formIsChanged = true;
            });
        });

        function focusEditor() {
            editor.focus();
        }

        function showRaw() {
            var description = $('#description').val();
            $('#raw-textarea').val(formatXml(description));
            $('#rawModal').modal('show');
        }

        function updateRaw() {
            var value = $('#raw-textarea').val();
            editor.setHTML(value);
            $('#description').val(value);
        }



        function serverPreview(field) {
            $('#templatePreviewModal').modal('show');
            var template = $('#descriptionEditor').val();
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

    </script>

    @include('partials.email_templates')
@stop
