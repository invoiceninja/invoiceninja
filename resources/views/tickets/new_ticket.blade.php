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
                'description' => 'required',
                'subject' => ' required',
            ]) !!}

    <div style="display:none">
        {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
        {!! Former::hidden('category_id')->value(1) !!}
        @if($parent_ticket)
            {!! Former::hidden('parent_ticket_id')->value($parent_ticket->public_id) !!}
        @endif
        {!! Former::hidden('status_id')->value(1) !!}
    </div>

    <div style="display:none">
        {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
    </div>

    <div class="panel panel-default">

        <div class="panel-heading">
            <h3 class="panel-title">{{ trans('texts.new_ticket') }}</h3>
        </div>

        <div class="panel-body">

            {{trans('texts.subject')}}
            {!! Former::small_text('subject')
                     ->label('')
                     ->id('subject')
                     ->style('width:100%;')
            !!}


            {{ trans('texts.description') }}

            {!! Former::textarea('description')->label(trans('texts.description'))->style('display:none')->raw() !!}

            <div id="descriptionEditor" class="form-control" style="min-height:160px; max-height:160px;" onclick="focusEditor()"></div>

            <div class="pull-left">
                @include('partials/quill_toolbar', ['name' => 'description'])
            </div>

        </div>

        <div class="panel-body">

            <div class="row">

                <div class="col-md-3">
                    {{ trans('texts.client') }}
                </div>

                <div class="col-md-9">

                    {!! Former::select('client_public_id')
                            ->label('')
                            ->addOption('', '')
                            ->data_bind("dropdown: client_public_id, dropdownOptions: {highlighter: comboboxHighlighter}")
                            ->addClass('pull-right')
                            ->addGroupClass('client-select') !!}

                </div>

            </div>

            <div class="row">

                <div class="col-md-3">
                    {{ trans('texts.agent') }}
                </div>

                <div class="col-md-9">
                    {!! Former::select('agent_id')
                           ->label('')
                           ->addOption('', '')
                           ->data_bind("dropdown: agent_id, dropdownOptions: {highlighter: comboboxHighlighter}")
                           ->addClass('pull-right')
                           ->addGroupClass('') !!}
                </div>

            </div>

            <div class="row">
                <div class="col-md-3">
                    {{ trans('texts.priority') }}
                </div>
                <div class="col-md-9">
                    {!! Former::select('priority_id')->label('')
                               ->fromQuery(\App\Models\Ticket::getPriorityArray(), 'name', 'id') !!}
                </div>
            </div>

            <div class="row">

                <div class="col-md-3">
                    {{trans('texts.due_date') }}
                </div>

                <div class="col-md-9">
                    <input id="due_date" type="text" data-bind="value: due_date.pretty" name="due_date"
                           class="form-control time-input time-input-end" placeholder="{{ trans('texts.due_date') }}"/>
                </div>

            </div>

            <div class="row" style="margin-top: 10px;">
                <div class="col-md-3">
                    {{ trans('texts.internal_ticket') }}
                </div>

                <div class="col-md-9">
                    {!! Former::checkbox('is_internal')
                                ->label('')
                                ->data_bind("checked: is_internal.pretty") !!}
                </div>
            </div>


            <div class="row" data-bind="visible: isParentTicketVisible()">

                <div class="col-md-3">
                    {{ trans('texts.parent_ticket') }}
                </div>

                <div class="col-md-9">

                    {!! Former::select('parent_ticket_id')
                            ->label('')
                            ->addOption('', '')
                            ->data_bind("dropdown: parent_ticket_id, dropdownOptions: {highlighter: comboboxHighlighter}")
                            ->addClass('pull-right')
                            ->addGroupClass('') !!}

                </div>

            </div>

        </div>

    </div>

    <div role="tabpanel" class="panel-default" style="margin-top:30px;">

        <ul class="nav nav-tabs" role="tablist" style="border: none">
            <li role="presentation" class="active"><a href="#private_notes" aria-controls="private_notes" role="tab" data-toggle="tab">{{ trans("texts.private_notes") }}</a></li>
            @if ($account->hasFeature(FEATURE_DOCUMENTS))
                <li role="presentation"><a href="#attached-documents" aria-controls="attached-documents" role="tab" data-toggle="tab">
                        {{ trans("texts.documents") }}

                    </a></li>
            @endif
        </ul>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 0) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 0) }}

        <div class="tab-content" style="padding-right:12px;">

            <div role="tabpanel" class="tab-pane active" id="private_notes" style="padding-bottom:44px">
                {!! Former::textarea('private_notes')
                        ->data_bind("value: private_notes, valueUpdate: 'afterkeydown'")
                        ->label(null)->style('width: 100%')->rows(4) !!}
            </div>

            <div role="tabpanel" class="tab-pane" id="attached-documents" style="position:relative; z-index:9;">
                <div id="document-upload">
                    <div class="dropzone">
                        <div data-bind="foreach: documents">
                            <input type="hidden" name="document_ids[]" data-bind="value: public_id"/>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 4) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 4) }}

    </div>

    <div class="row">
        <center class="buttons">
            {!! Button::primary(trans('texts.create_ticket'))->large()->withAttributes(['onclick' => 'saveAction()']) !!}
        </center>
    </div>

    {!! Former::close() !!}


    <script type="text/javascript">

        $( function() {
            $("#subject").focus();

            window.model = new ViewModel({!! json_encode($old) !!});

            <!----- Client Selector ----->
            var clients = {!! $clients !!};
            var clientMap = {};
            var $clientSelect = $('select#client_public_id');

            // create client dictionary
            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
                clientMap[client.public_id] = client;

                if (!getClientDisplayName(client)) {
                    continue;
                }

                var clientName = client.name || '';
                for (var j=0; j<client.contacts.length; j++) {
                    var contact = client.contacts[j];
                    var contactName = getContactDisplayNameWithEmail(contact);
                    if (clientName && contactName) {
                        clientName += ' • ';
                    }
                    if (contactName) {
                        clientName += contactName;
                    }
                }
                $clientSelect.append(new Option(clientName, client.public_id));
            }

            //harvest and set the client_id and contact_id here
            var $input = $('select#client_public_id');
            $input.combobox().on('change', function(e) {
                var clientId = parseInt($('input[name=client_public_id]').val(), 10) || 0;

                if (clientId > 0) {

                    model.client_public_id(clientId);

                }
            });

            <!----- User Selector ----->
            var users = {!! $account->users !!};
            var userMap = {};
            var $userSelect = $('select#agent_id');

            // create user dictionary
            for (var i=0; i<users.length; i++) {
                var user = users[i];
                userMap[user.id] = user;

                var userName = user.first_name + ' ' + user.last_name || '';
                $userSelect.append(new Option(userName, user.id));
            }

            //harvest and set the agent_id here
            var $input = $('select#agent_id');
            $input.combobox().on('change', function(e) {
                var agentId = parseInt($('input[name=agent_id]').val(), 10) || 0;

                if (agentId > 0) {

                    model.agent_id(agentId);

                }
            });

            <!----- Parent Ticket Selector ----->
            var parent_tickets = {!! $parent_tickets !!};
            var ticketMap = {};
            var $ticketSelect = $('select#parent_ticket_id');

            // create client dictionary
            for (var i=0; i<parent_tickets.length; i++) {
                var ticket = parent_tickets[i];
                ticketMap[ticket.public_id] = ticket;

                var ticketName = '{{ trans('texts.ticket_number') }}' + '' + ticket.ticket_number;
                $ticketSelect.append(new Option(ticketName, ticket.public_id));
            }

            //harvest and set the parent_ticket_id here
            var $input = $('select#parent_ticket_id');
            $input.combobox().on('change', function(e) {
                var parentTicketId = parseInt($('input[name=parent_ticket_id]').val(), 10) || 0;

                if (parentTicketId > 0) {

                    model.parent_ticket_id(parentTicketId);

                }
            });


            ko.applyBindings(model);

            @include('partials.dropzone', ['documentSource' => 'model.documents()'])

        });

        <!-- Initialize drop zone file uploader -->
        $('.main-form').submit(function(){
            if($('#document-upload .dropzone .fallback input').val())$(this).attr('enctype', 'multipart/form-data')
            else $(this).removeAttr('enctype')
        })

        <!-- Initialize date time picker for due date -->
        jQuery('#due_date').datetimepicker({
            lazyInit: true,
            validateOnBlur: false,
            step: '{{ env('TASK_TIME_STEP', 15) }}',
            minDate: 'moment()',
            validateOnBlur: false
        });

        var ViewModel = function (data) {
            var self = this;

            console.log(data);

            var dateTimeFormat = '{{ $datetimeFormat }}';
            var timezone = '{{ $timezone }}';

            self.documents = ko.observableArray();
            self.due_date = ko.observable(data.due_date);
            self.priority_id = ko.observable(data.priority_id);
            self.agent_id = ko.observable(data.agent_id);
            self.is_internal = ko.observable(data.is_internal);
            self.subject = ko.observable(data.subject);
            self.description = ko.observable(data.description);
            self.client_public_id = ko.observable(data.client_public_id);
            self.parent_ticket_id = ko.observable(data.parent_ticket_id);
            self.private_notes = ko.observable(data.private_notes);

            self.mapping = {
                'documents': {
                    create: function (options) {
                        return new DocumentModel(options.data);
                    }
                }
            }

            self.isParentTicketVisible = function() {
                return self.is_internal.pretty;
            }

            self.is_internal.pretty = ko.computed({
                read: function() {
                    if(self.is_internal() == 0)
                        return false;
                    else
                        return true;
                },
                write: function(newValue) {
                    model.is_internal(newValue ? true : false);
                }

            });

            self.due_date.pretty = ko.computed({
                read: function() {
                    return self.due_date() ? moment(self.due_date()).format(dateTimeFormat) : '';
                },
                write: function(data) {
                    self.due_date(moment(data).format("YYYY-MM-DD HH:mm:ss"));
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



        function saveAction() {

            var dateTimeFormat = '{{ $datetimeFormat }}';

            if($('#due_date').val().length > 1)
                $('#due_date').val(moment($('#due_date').val(), dateTimeFormat).format("YYYY-MM-DD HH:mm:ss"));

            $('.main-form').submit();
        }
    </script>

@stop