@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/jquery.datetimepicker.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/jquery.datetimepicker.css') }}" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

    <script src="{{ asset('js/tinymce.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/tinymce-mentions-plugin.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/tinymce-mentions-autocomplete.css') }}" rel="stylesheet" type="text/css"/>



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
            ]) !!}

    @if ($ticket)
        {!! Former::populate($ticket) !!}
    @endif

    <div style="display:none">
        {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
        {!! Former::hidden('category_id')->value(1) !!}
        @if($ticket)
            {!! Former::hidden('public_id')->value($ticket->public_id) !!}
            {!! Former::hidden('status_id')->value($ticket->status_id)->id('status_id') !!}
            {!! Former::hidden('closed')->value($ticket->closed)->id('closed') !!}
            {!! Former::hidden('reopened')->value($ticket->reopened)->id('reopened') !!}
            {!! Former::hidden('subject')->value($ticket->subject)->id('subject') !!}
            {!! Former::hidden('contact_key')->value($ticket->contact_key)->id('contact_key') !!}
            {!! Former::hidden('is_internal')->value($ticket->is_internal) !!}
        @else
            {!! Former::hidden('status_id')->value(1) !!}
        @endif
    </div>

    <div class="panel panel-default">
        @if($isAdminUser)
            @include('tickets.partials.ticket_meta_data_admin')
        @else
            @include('tickets.partials.ticket_meta_data_agent')
        @endif
    </div>

    @if($ticket && $ticket->is_internal == true)
        <div class="panel panel-default">
            <center class="buttons">
                <h3>{!! trans('texts.internal_ticket') !!}</h3>
            </center>
            <table width="100%">
                <tr>
                    <td width="50%" style="vertical-align:top;">
                        <table class="table table-striped datatable">
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

    @if(!$ticket->merged_parent_ticket_id)

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

                    <textarea id="description" name="description"></textarea>


            </div>
        </div>

        </div>
        <div id='msgs' class='column' style='font-size:10pt;font-weight:normal;color:red;'>

        @can('edit', $ticket)
        <div class="row">
            <center class="buttons">

                @if(!$ticket->is_internal && $ticket->client)
                    {!! DropdownButton::normal(trans('texts.more_actions'))
                    ->withContents([
                        ['label'=>trans('texts.ticket_merge'),'url'=>'/tickets/merge/'. $ticket->public_id ],
                        ['label'=>trans('texts.new_internal_ticket'), 'url'=>'/tickets/create/'.$ticket->public_id],
                    ])
                    ->large()
                    ->dropup() !!}
                @endif

                @if($ticket && $ticket->status_id == 3)
                    {!! Button::warning(trans('texts.ticket_reopen'))->large()->withAttributes(['onclick' => 'reopenAction()']) !!}
                @elseif(!$ticket)
                    {!! Button::primary(trans('texts.ticket_open'))->large()->withAttributes(['onclick' => 'submitAction()']) !!}
                @else
                    {!! Button::danger(trans('texts.ticket_close'))->large()->withAttributes(['onclick' => 'closeAction()']) !!}
                    {!! Button::primary(trans('texts.ticket_update'))->large()->withAttributes(['onclick' => 'submitAction()']) !!}
                @endif

            </center>
        </div>
        @endcan

    @endif

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
            <li role="presentation"><a href="#linked_objects" aria-controls="linked_objects" role="tab" data-toggle="tab">{{ trans("texts.linked_objects") }}</a></li>
        </ul>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 0) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 0) }}

        <div class="tab-content" style="padding-right:12px;">

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

            <div row="tabpanel" class="tab-pane" id="linked_objects" style="width:600px;min-height: 300px; height: auto !important;">

                <div style="">

                    <div style="float:left;margin:10px;">
                        {!! Former::select('linked_object')
                            ->style('width:170px;padding:10px;')
                            ->label('')
                            ->text(trans('texts.type'))
                            ->addOption('', '')
                            ->fromQuery(\App\Models\Ticket::relationEntities())
                            ->data_bind("event: {change: onEntityChange }")
                         !!}
                    </div>

                    <div style="float:left;margin:10px;">
                        {!! Former::select('linked_item')
                            ->style('width:170px;padding:10px;')
                            ->label('')
                            ->text(trans('texts.type'))
                            ->addOption('', '')
                            ->data_bind("options: entityItems")
                         !!}
                    </div>

                    <div style="float:left;margin:10px;">
                        {!! Button::normal(trans('texts.link'))
                                    ->small()
                                    ->withAttributes(['onclick' => 'addRelation()', 'data-bind' => 'enable: checkObjectAndItemExist']) !!}
                    </div>

                </div>

                <div style="clear:both; float:left;">
                    <ul data-bind="foreach: relations">
                        <li data-bind="html: entity_url"></li>
                    </ul>
                </div>


            </div>


        </div>
        @if(!$ticket->merged_parent_ticket_id && Auth::user()->can('edit', $ticket))
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

        tinymce.init({
            selector: '#description',
            plugins : "mention",
            mentions: {
                delimiter: '#',
                queryBy: 'description',
                source: function (query, process, delimiter) {
                    // Do your ajax call
                    // When using multiple delimiters you can alter the query depending on the delimiter used
                    if (delimiter === '#') {
                        $.ajax({
                            type: "POST",
                            url:"/tickets/search",
                            data: {term: query},
                            success: function (msg, status, jqXHR) {
                                process(msg);
                            },
                            dataType: 'json'
                        });
                    }
                }
            },

        });

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

            self.client_public_id = ko.observable();
            self.documents = ko.observableArray();
            self.due_date = ko.observable(data.due_date);
            self.mapping = {
                'documents': {
                    create: function (options) {
                        return new DocumentModel(options.data);
                    }
                }
            }

            self.relations = ko.observableArray({!! $ticket->relations !!});
            self.entityItems = ko.observableArray();
            self.checkObjectAndItemExist = ko.observable(false);

            self.due_date.pretty = ko.computed({
                read: function() {

                    if(self.due_date() == '0000-00-00 00:00:00')
                        return;
                    else
                        return self.due_date() ? moment(self.due_date()).format(dateTimeFormat) : '';

                },
                write: function(data) {
                    self.due_date(moment($('#due_date').val(), dateTimeFormat, timezone).format("YYYY-MM-DD HH:mm:ss"));

                }
            });

            self.isAdminUser = ko.observable({!! $isAdminUser !!});

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


            self.onEntityChange = function(obj, event) {

                getItemsForEntity();

            }

        };


        function buildEntityList(data) {
            model.entityItems([]);

            for(j=0; j<data.length; j++) {
                model.entityItems.push(data[j].public_id);
            }

            if (data.length > 0)
                model.checkObjectAndItemExist(true);
            else
                model.checkObjectAndItemExist(false);

        }

        function getItemsForEntity()
        {
            model.entityItems([]);
            model.checkObjectAndItemExist(false);

            var linked_object = $('#linked_object').val();
            var ticket_id = {{ $ticket->id }};
            var account_id = {{ $account->id }};
            var client_public_id = {{$ticket->client ? $ticket->client->public_id : 'null'}};

            if(!linked_object)
                return;

            var obj = { client_public_id: client_public_id, account_id: account_id, entity: linked_object, ticket_id: ticket_id };

            $.ajax({
                url: "/tickets/entities/",
                type: "GET",
                data: obj,
                success: function (result) {
                    buildEntityList(result);
                }
            });

        }

        function addRelation()
        {
            var linked_object = $('#linked_object').val();
            var linked_item = $('#linked_item').val()
            var ticket_id = {{ $ticket->id }};

            var obj = { entity: linked_object, entity_id: linked_item, ticket_id: ticket_id };

            $.ajax({
                url: "/tickets/entities/create",
                type: "POST",
                data: obj,
                success: function (result) {

                    if(!result.entity_url)
                        return alert('{{ trans('texts.error_title') }}');

                    model.relations.push(result);
                    getItemsForEntity();

                }
            });
        }

        function removeRelation(entityId)
        {
            var obj = {id : entityId};

            $.ajax({
                url: "/tickets/entities/remove",
                type: "POST",
                data: obj,
                success: function (relationId) {

                    if(!relationId)
                        return alert('{{ trans('texts.error_title') }}');

                    model.relations.remove(function(relation) {
                        return relation.id == relationId;
                    });

                    getItemsForEntity();

                }
            });


        }


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

            if($('#due_date').val().length > 1)
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

            if( tinyMCE.activeEditor.getContent({format : 'raw'}).length < 1 ) {
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



        <!-- Initialize client selector -->
                @if($clients)

        var clients = {!! $clients !!};
        var clientMap = {};
        var $clientSelect = $('select#client_public_id');

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
        var $input = $('select#client_public_id');
        $input.combobox().on('change', function(e) {
            var clientId = parseInt($('input[name=client_public_id]').val(), 10) || 0;

            if (clientId > 0) {

                for (var j=0; j<client.contacts.length; j++) {
                    var contact = client.contacts[j];

                    if(contact.email == $('#contact_key').val()) {
                        $('#contact_key').val(contact.contact_key);
                        $('#client_public_id').val(clientId);
                    }
                }
            }
        });

        @endif

    </script>

@stop