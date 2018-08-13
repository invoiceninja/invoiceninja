@extends('header')

@section('head')
@parent

    <script src="{{ asset('js/jquery.datetimepicker.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/jquery.datetimepicker.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

@stop

<style>
    .td-left {width:1%; white-space:nowrap; text-align: right; height:40px;}
    .td-right {width:1%; white-space:nowrap; text-align: left; height:40px;}
    #accordion .ui-accordion-header {background: #033e5e; color: #fff;}
</style>

@section('content')

    {!! Former::open($url)
            ->addClass('col-lg-10 col-lg-offset-1 warn-on-exit main-form')
            ->autocomplete('off')
            ->method($method)
            ->rules([
                'name' => 'required',
                'client_id' => 'required',
            ]) !!}

    @if ($ticket)
        {!! Former::populate($ticket) !!}
    @endif

    <div style="display:none">
        {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
        {!! Former::hidden('account_id')->value($account->id) !!}
        {!! Former::hidden('category_id')->value(1) !!}
        @if($ticket)
            {!! Former::hidden('public_id')->value($ticket->public_id) !!}
            {!! Former::hidden('status_id')->value($ticket->status_id)->id('status_id') !!}
            {!! Former::hidden('closed')->value($ticket->closed)->id('closed') !!}
            {!! Former::hidden('reopened')->value($ticket->reopened)->id('reopened') !!}
            {!! Former::hidden('subject')->value($ticket->subject)->id('subject') !!}
            {!! Former::hidden('contact_key')->value($ticket->contact_key)->id('contact_key') !!}
            {!! Former::hidden('client_id')->value($ticket->client_id)->id('client_id') !!}
        @else
            {!! Former::hidden('status_id')->value(1) !!}
        @endif
    </div>

    <div style="display:none">
        {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
    </div>

    <div class="panel panel-default">
        <table width="100%">
            <tr>
                <td width="50%" style="vertical-align:top;">
                    <table class="table table-striped datatable">
                        <tbody>
                        <tr><td class="td-left">{!! trans('texts.ticket_number')!!}</td><td class="td-right">{!! $ticket->ticket_number !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.category') !!}:</td><td class="td-right">{!! $ticket->category->name !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.subject')!!}:</td><td class="td-right">{!! substr($ticket->subject, 0, 30) !!}</td></tr>
                        @if($ticket->client_id)
                            <tr><td class="td-left" style="height:60px">{!! trans('texts.client') !!}:</td><td class="td-right">{!! $ticket->client->name !!}</td></tr>
                        @else
                            <tr><td class="td-left" style="height:60px">{!! trans('texts.client') !!}:</td>
                                <td class="td-right">
                                    {!! Former::select('client')
                                    ->label('')
                                    ->addOption('', '')
                                    ->data_bind("dropdown: client, dropdownOptions: {highlighter: comboboxHighlighter}")
                                    ->addClass('client-input')
                                    ->addGroupClass('client_select closer-row') !!}
                                </td></tr>
                        @endif

                        <tr><td class="td-left" style="height:77px">{!! trans('texts.contact') !!}:</td><td class="td-right">{!! $ticket->getContactName() !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.assigned_to') !!}:</td><td class="td-right">
                                @if(Auth::user()->id == Auth::user()->account->account_ticket_settings->ticket_master->id)
                                    <div id="">
                                        {!! Former::select('agent_id')
                                            ->label('')
                                            ->text(trans('texts.ticket_master'))
                                            ->fromQuery($account->users, 'displayName', 'id')
                                         !!}
                                    </div>
                                @else
                                    {!! $ticket->agent() !!} {!! Icon::create('random') !!}
                                @endif
                            </td></tr>
                        </tbody>
                    </table>
                </td>
                <td width="50%" style="vertical-align:top;">
                    <table class="table table-striped datatable">
                        <tbody>
                        <tr><td class="td-left">{!! trans('texts.created_at') !!}:</td><td class="td-right">{!! \App\Libraries\Utils::fromSqlDateTime($ticket->created_at) !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.last_updated') !!}:</td><td class="td-right">{!! \App\Libraries\Utils::fromSqlDateTime($ticket->updated_at) !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.status') !!}:</td><td class="td-right"> {!! $ticket->getStatusName() !!} </td></tr>

                        <tr><td class="td-left">{!! trans('texts.due_date') !!}:</td>
                            <td class="td-right">
                                <input id="due_date" type="text" data-bind="value: due_date.pretty" name="due_date"
                                       class="form-control time-input time-input-end" placeholder="{{ trans('texts.due_date') }}"/>
                            </td>
                        </tr>
                        <tr><td class="td-left">{!! trans('texts.priority') !!}:</td>
                            <td class="td-right">
                                {!! Former::select('priority_id')->label('')
                                ->fromQuery(\App\Models\Ticket::getPriorityArray(), 'name', 'id') !!}
                            </td>
                        </tr>

                        @if(!$ticket->merged_parent_ticket_id)
                            <tr>
                                <td></td>
                                <td><span class="pull-right">
                                        {!! Button::primary(trans('texts.save'))->small()->withAttributes(['onclick' => 'saveAction()']) !!}
                                    </span></td>
                            </tr>
                        @else
                            <tr>
                                <td class="td-left">{!! trans('texts.parent_ticket') !!}:</td>
                                <td> {!!  link_to("tickets/{$ticket->merged_ticket_parent->public_id}", $ticket->merged_ticket_parent->public_id ?: '')->toHtml() !!}
                                </td>
                            </tr>
                        @endif

                        @if(count($ticket->merged_children) > 0)
                            <tr>
                                <td class="td-left">{!! trans('texts.linked_tickets') !!}:</td>
                                <td>
                                    @foreach($ticket->merged_children as $child)
                                       {{ trans('texts.ticket_number') }} {!! link_to("tickets/{$child->public_id}", $child->public_id ?: '')->toHtml() !!} <br>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>




    @if($ticket)
        <div style="height:80px;">
            <div class="pull-right">
                {!! Button::info(trans('texts.show_hide_all'))->large()->withAttributes(['onclick' => 'toggleAllComments()']) !!}
            </div>
        </div>
    @endif

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

            <div id="descriptionEditor" class="form-control" style="max-height:300px;" onclick="focusEditor()"></div>

            <div class="pull-left">
                @include('partials/quill_toolbar', ['name' => 'description'])
            </div>

        </div>

    </div>

    <div class="row">
        <center class="buttons">
            @if(!$ticket->merged_parent_ticket_id)
            {!! DropdownButton::normal(trans('texts.more_actions'))
            ->withContents([
                ['label'=>trans('texts.ticket_merge'),'url'=>'/tickets/merge/'. $ticket->public_id ],
                ['label'=>trans('texts.new_internal_ticket'), 'url'=>'/tickets/create/'.$ticket->public_id],
            ])
            ->large()
            ->dropup() !!}


                @if($ticket && $ticket->status->id == 3)
                    {!! Button::warning(trans('texts.ticket_reopen'))->large()->withAttributes(['onclick' => 'reopenAction()']) !!}
                @elseif(!$ticket)
                    {!! Button::primary(trans('texts.ticket_open'))->large()->withAttributes(['onclick' => 'submitAction()']) !!}
                @else
                    {!! Button::danger(trans('texts.ticket_close'))->large()->withAttributes(['onclick' => 'closeAction()']) !!}
                    {!! Button::primary(trans('texts.ticket_update'))->large()->withAttributes(['onclick' => 'submitAction()']) !!}
                @endif

            @endif
        </center>
    </div>

    <div role="tabpanel" class="panel-default" style="margin-top:30px;">

        <ul class="nav nav-tabs" role="tablist" style="border: none">
            <li role="presentation" class="active"><a href="#private_notes" aria-controls="private_notes" role="tab" data-toggle="tab">{{ trans("texts.private_notes") }}</a></li>
            @if ($account->hasFeature(FEATURE_DOCUMENTS))
                <li role="presentation"><a href="#attached-documents" aria-controls="attached-documents" role="tab" data-toggle="tab">
                        {{ trans("texts.documents") }}
                        @if ($ticket->documents()->count() >= 1)
                            ({{ $ticket->documents()->count() }})
                        @endif
                    </a></li>
            @endif
        </ul>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 0) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 0) }}

        <div class="tab-content" style="padding-right:12px;max-width:600px;">

            <div role="tabpanel" class="tab-pane active" id="private_notes" style="padding-bottom:44px">
                {!! Former::textarea('private_notes')
                        ->data_bind("value: private_notes, valueUpdate: 'afterkeydown'")
                        ->label(null)->style('width: 100%')->rows(4) !!}
            </div>

            <div role="tabpanel" class="tab-pane" id="attached-documents" style="position:relative;z-index:9">
                <div id="document-upload">
                    <div class="dropzone">
                        <div data-bind="foreach: documents">
                            <input type="hidden" name="document_ids[]" data-bind="value: public_id"/>
                        </div>
                    </div>
                    @if ($ticket->documents())
                        @foreach($ticket->documents() as $document)
                            <div>{{$document->name}}</div>
                        @endforeach
                    @endif
                </div>
            </div>


        </div>
        @if(!$ticket->merged_parent_ticket_id)
        <div class="pull-right">
            {!! Button::primary(trans('texts.save'))->large()->withAttributes(['onclick' => 'saveAction()']) !!}
        </div>
        @endif
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

        <!-- Initialize client selector -->
        @if($clients)

        var clients = {!! $clients !!};
        var clientMap = {};
        var $clientSelect = $('select#client');

        $(function() {
            // create client dictionary

            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
                clientMap[client.public_id] = client;
                @if (! $ticket->id)
                if (!getClientDisplayName(client)) {
                    continue;
                }
                        @endif
                var clientName = client.name || '';
                for (var j=0; j<client.contacts.length; j++) {
                    var contact = client.contacts[j];
                    var contactName = getContactDisplayNameWithEmail(contact);
                    if (clientName && contactName) {
                        clientName += '<br/>  • ';
                    }
                    if (contactName) {
                        clientName += contactName;
                    }
                }
                $clientSelect.append(new Option(clientName, client.public_id));
            }

            //harvest and set the client_id and contact_id here
            var $input = $('select#client');
            $input.combobox().on('change', function(e) {
                var clientId = parseInt($('input[name=client]').val(), 10) || 0;

                if (clientId > 0) {

                    for (var j=0; j<client.contacts.length; j++) {
                        var contact = client.contacts[j];

                        if(contact.email == $('#contact_key').val()) {
                            $('#contact_key').val(contact.contact_key);
                            $('#client_id').val(clientId);
                        }
                    }
                }
            });

        });
        @endif


        <!-- Initialize ticket_comment accordion -->
        $( function() {
            $( "#accordion" ).accordion();

            window.model = new ViewModel({!! $ticket !!});
            ko.applyBindings(model);
            $('#description').text('');

            @include('partials.dropzone', ['documentSource' => 'model.documents()'])

        } );

        // Add moment support to the datetimepicker
        Date.parseDate = function( input, format ){
            return moment(input, format).toDate();
        };
        Date.prototype.dateFormat = function( format ){
            return moment(this).format(format);
        };

        <!-- Initialize date time picker for due date -->
        jQuery('#due_date').datetimepicker({
            lazyInit: true,
            validateOnBlur: false,
            step: '{{ env('TASK_TIME_STEP', 15) }}',
            minDate: '{{ $ticket->getMinDueDate() }}',
            format: '{{ $datetimeFormat }}',
            formatDate: '{{ $account->getMomentDateFormat() }}',
            formatTime: '{{ $account->military_time ? 'H:mm' : 'h:mm A' }}',
            validateOnBlur: false
        });


        <!-- Initialize drop zone file uploader -->
        $('.main-form').submit(function(){
            if($('#document-upload .dropzone .fallback input').val())$(this).attr('enctype', 'multipart/form-data')
            else $(this).removeAttr('enctype')
        })

        var ViewModel = function (data) {
            var self = this;
            var dateTimeFormat = '{{ $datetimeFormat }}';
            var timezone = '{{ $timezone }}';

            self.documents = ko.observableArray();
            self.due_date = ko.observable(data.due_date);
            self.mapping = {
                'documents': {
                    create: function (options) {
                        return new DocumentModel(options.data);
                    }
                }
            }

            self.due_date.pretty = ko.computed({
                read: function() {
                    return self.due_date() ? moment(self.due_date()).format(dateTimeFormat) : '';
                },
                write: function(data) {
                    self.due_date(moment($('#due_date').val(), dateTimeFormat, timezone).format("YYYY-MM-DD HH:mm:ss"));

                }
            });


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

        function toggleAllComments() {
            $(".ui-accordion-content").toggle();
        }


        function saveAction() {

            var dateTimeFormat = '{{ $datetimeFormat }}';
            $('#due_date').val(moment($('#due_date').val(), dateTimeFormat).format("YYYY-MM-DD HH:mm:ss"));
            $('.main-form').submit();
        }

        function submitAction() {

            if(checkCommentText('{{ trans('texts.enter_ticket_message') }}')) {
                saveAction();
            }

        }

        function reopenAction() {

            if(checkCommentText('{{ trans('texts.reopen_reason') }}')){
                $('#reopened').val(moment().format("YYYY-MM-DD HH:mm:ss"));
                $('#closed').val(null);
                $('#status_id').val(2);
                saveAction();
            }

        }

        function closeAction() {
            if(checkCommentText('{{ trans('texts.close_reason') }}')) {
                $('#closed').val(moment().format("YYYY-MM-DD HH:mm:ss"));
                $('#reopened').val(null);
                $('#status_id').val(3);
                saveAction();
            }

        }

        function checkCommentText(errorString) {

            if( $('#description').val().length < 1 ) {
                $('#ticket_message').text(errorString);
                $('#errorModal').modal('show');

                return false;
            }
            else if($('#subject').val().length < 1 ) {
                $('#ticket_message').text('{{ trans('texts.subject_required') }}');
                $('#errorModal').modal('show');
            }
            else {
                return true;
            }

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