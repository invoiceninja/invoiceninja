@extends('public.header')

@section('content')
    @parent
    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

    <style type="text/css">
        textarea {
            min-height: 150px !important;
        }

        .td-left {width:1%; white-space:nowrap; text-align: right;}
        #accordion .ui-accordion-header {background: #033e5e; color: #fff;}


    </style>

    {!! Former::open($url)
            ->addClass('col-lg-10 col-lg-offset-1 warn-on-exit main-form')
            ->autocomplete('off')
            ->method($method)
            ->rules([
                'name' => 'required',
                'client_id' => 'required',
                'subject' => 'required',
            ]) !!}

    @if ($ticket)
        {!! Former::populate($ticket) !!}
    @endif

    <div style="display:none">
        {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
        {!! Former::hidden('account_id')->value($account->id) !!}
        {!! Former::hidden('category_id')->value(1) !!}
        {!! Former::hidden('status_id')->value(1) !!}
        @if($ticket)
        {!! Former::hidden('public_id')->value($ticket->public_id) !!}
        {!! Former::hidden('status_id')->value($ticket->status_id)->id('status_id') !!}
        {!! Former::hidden('closed')->value($ticket->closed)->id('closed') !!}
        {!! Former::hidden('reopened')->value($ticket->reopened)->id('reopened') !!}
        {!! Former::hidden('subject')->value($ticket->subject)->id('subject') !!}
        @endif
    </div>

    @if($ticket)
    <div class="panel panel-default">
        <table width="100%">
            <tr>
                <td width="50%">
                    <table class="table table-striped dataTable" >
                        <tbody>
                        <tr><td class="td-left">{!! trans('texts.ticket_number')!!}</td><td>{!! $ticket->ticket_number !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.category') !!}:</td><td>{!! $ticket->category->name !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.subject')!!}:</td><td>{!! substr($ticket->subject, 0, 30) !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.assigned_to') !!}:</td><td>{!! $ticket->agentName() !!}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td width="50%">
                    <table class="table table-striped dataTable" >
                        <tbody>
                        <tr><td class="td-left">{!! trans('texts.created_at') !!}:</td><td>{!! \App\Libraries\Utils::fromSqlDateTime($ticket->created_at) !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.last_updated') !!}:</td><td>{!! \App\Libraries\Utils::fromSqlDateTime($ticket->updated_at) !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.status') !!}:</td><td>{{ $ticket->status->name }}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.priority') !!}:</td><td>{{ $ticket->getPriorityName() }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    @endif

    @if($ticket)
    <div style="height:80px;">
        <div class="pull-right">
            {!! Button::info(trans('texts.show_hide_all'))->large()->withAttributes(['onclick' => 'toggleAllComments()']) !!}
        </div>
    </div>

    <div class="panel-default ui-accordion ui-widget ui-helper-reset" id="accordion" role="tablist">
        @foreach($ticket->comments as $comment)
            <h3 class="ui-accordion-header ui-corner-top ui-state-default ui-accordion-header-active ui-state-active ui-accordion-icons" role="tab" id="accordion">{!! $comment->getCommentHeader() !!}</h3>
            <div>
                <p>
                    {!! $comment->description !!}
                </p>
            </div>
        @endforeach
    </div>
    @endif

    <div class="panel panel-default" style="margin-top:30px; padding-bottom: 0px !important">
        <div class="panel-heading">
            <h3 class="panel-title">
                @if($ticket)
                    {!! trans('texts.reply') !!}
                @else
                    {!! trans('texts.new_ticket') !!}
                @endif</h3>
        </div>

        <div class="panel-body">

            @if(!$ticket)
            {{trans('texts.subject')}}
            {!! Former::small_text('subject')
                     ->label('')
                     ->id('subject')
                     ->style('width:100%;')
            !!}


            {{ trans('texts.description') }}
            @endif
            {!! Former::textarea('description')->label(trans('texts.description'))->style('display:none')->raw() !!}

            <div id="descriptionEditor" class="form-control" style="min-height:160px" onclick="focusEditor()"></div>

            <div class="pull-left">
                @include('partials/quill_toolbar', ['name' => 'description'])
            </div>

        </div>

    </div>

    <div class="row">
        <center class="buttons">
            @if($ticket && $ticket->status->id == 3)
            {!! Button::warning(trans('texts.ticket_reopen'))->large()->withAttributes(['onclick' => 'reopenAction()']) !!}
            @elseif(!$ticket)
            {!! Button::primary(trans('texts.ticket_open'))->large()->withAttributes(['onclick' => 'submitAction()']) !!}
            @else
            {!! Button::danger(trans('texts.ticket_close'))->large()->withAttributes(['onclick' => 'closeAction()']) !!}
            {!! Button::primary(trans('texts.ticket_update'))->large()->withAttributes(['onclick' => 'submitAction()']) !!}
            @endif
        </center>
    </div>

    <div role="tabpanel" class="panel panel-default" style="margin-top:30px;">

        <ul class="nav nav-tabs" role="tablist" style="border: none">
            @if ($account->hasFeature(FEATURE_DOCUMENTS))
                <li role="presentation" class="active"><a href="#attached-documents" aria-controls="attached-documents" role="tab" data-toggle="tab">
                        {{ trans("texts.documents") }}
                        @if ($ticket && $ticket->documents()->count() >= 1)
                            ({{ $ticket->documents()->count() }})
                        @endif
                    </a></li>
            @endif
        </ul>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 0) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 0) }}

        <div class="tab-content" style="padding-right:12px;">

            <div role="tabpanel" class="tab-pane active" id="attached-documents" style="position:relative; z-index:9;">
                <div id="document-upload">
                    <div class="dropzone">
                        <div data-bind="foreach: documents">
                            <input type="hidden" name="document_ids[]" data-bind="value: public_id"/>
                        </div>
                    </div>
                    @if ($ticket && $ticket->documents())
                        @foreach($ticket->documents() as $document)
                            <div>{{$document->name}}</div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 4) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 4) }}


    </div>
    {!! Former::close() !!}



    <!--
   Modals
    -->



    <div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="error" aria-hidden="true">
        <div class="modal-dialog" style="min-width:150px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="recurringModalLabel">{{ trans('texts.error_title') }}</h4>
                </div>

                <div class="container" style="width: 100%; padding-bottom: 0px !important">
                    <div class="panel panel-default">
                        <div class="panel-body" id="ticket_message">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
                </div>

            </div>
        </div>
    </div>

    <script type="text/javascript">

        <!-- Initialize ticket_comment accordion -->
        $( function() {
            $( "#accordion" ).accordion();

            window.model = new ViewModel({!! $ticket !!});

            ko.applyBindings(model);

            @include('partials.client_dropzone', ['documentSource' => 'model.documents()', 'account_ticket_settings' => $account_ticket_settings])


            $('#description').text('');

        } );


        <!-- Initialize drop zone file uploader -->
        $('.main-form').submit(function(){
            if($('#document-upload .dropzone .fallback input').val())$(this).attr('enctype', 'multipart/form-data')
            else $(this).removeAttr('enctype')
        })

        var ViewModel = function (data) {
            var self = this;

            self.documents = ko.observableArray();

            self.mapping = {
                'documents': {
                    create: function (options) {
                        return new DocumentModel(options.data);
                    }
                }
            }

            if (data) {
                ko.mapping.fromJS(data, self.mapping, this);
            }

            self.addDocument = function() {
                var documentModel = new DocumentModel();
                self.documents.push(documentModel);
                return documentModel;
            }

            self.removeDocument = function(doc) {
                var public_id = doc.public_id?doc.public_id():doc;
                self.documents.remove(function(document) {
                    return document.public_id() == public_id;
                });
            }
        };


        function DocumentModel(data) {
            var self = this;
            self.public_id = ko.observable(0);
            self.size = ko.observable(0);
            self.name = ko.observable('');
            self.type = ko.observable('');
            self.url = ko.observable('');

            self.update = function(data){
                ko.mapping.fromJS(data, {}, this);
            }

            if (data) {
                self.update(data);
            }
        }

        function addDocument(file) {
            file.index = model.documents().length;
            model.addDocument({name:file.name, size:file.size, type:file.type});
        }

        function addedDocument(file, response) {
            model.documents()[file.index].update(response.document);
        }

        function deleteDocument(file) {
            model.removeDocument(file.public_id);
        }

        function submitAction() {

            if(checkCommentText('{{ trans('texts.enter_ticket_message') }}')) {
                $('.main-form').submit();
            }

        }

        function reopenAction() {

            if(checkCommentText('{{ trans('texts.reopen_reason') }}')){
                $('#reopened').val(new Date().toISOString().slice(0, 19).replace('T', ' '));
                $('#closed').val(null);
                $('#status_id').val(2);
                $('.main-form').submit();
            }

        }

        function closeAction() {
            if(checkCommentText('{{ trans('texts.close_reason') }}')) {
                $('#closed').val(new Date().toISOString().slice(0, 19).replace('T', ' '));
                $('#reopened').val(null);
                $('#status_id').val(3);
                $('.main-form').submit();
            }

        }

        function checkCommentText(errorString) {

            if( $('#description').val().length < 1 ) {
                $('#ticket_message').text(errorString);
                $('#errorModal').modal('show');

                return false;
            }
            else if( $('#subject').val().length < 1 ) {
                $('#ticket_message').text('{{ trans('texts.subject_required') }}');
                $('#errorModal').modal('show');
            }
            else {
                return true;
            }


        }

        function toggleAllComments() {
            $(".ui-accordion-content").toggle();
        }


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

    </script>

@stop