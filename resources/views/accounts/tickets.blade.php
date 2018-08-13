@extends('header')

@section('content')
    @parent


    {!! Former::open_for_files()
            ->addClass('warn-on-exit')
            ->autocomplete('on')
            ->rules([])
    !!}

    {{ Former::populate($account_ticket_settings) }}
    {{ Former::populateField('local_part', $account_ticket_settings->support_email_local_part) }}

    @include('accounts.nav', ['selected' => ACCOUNT_TICKETS])

    <div class="row">
        <div class="col-md-12">

            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active"><a href="#defaults" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.defaults') }}</a></li>
                    <li role="presentation"><a href="#domain" aria-controls="terms" role="tab" data-toggle="tab">{{ trans('texts.domain') }}</a></li>
                    <li role="presentation"><a href="#attachments" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.attachments') }}</a></li>
                    <li role="presentation"><a href="#notifications" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.notifications') }}</a></li>
                    <li role="presentation"><a href="#templates" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.templates') }}</a></li>
                </ul>

                <div class="tab-content panel">

                    <div role="tabpanel" class="tab-pane active" id="defaults" >
                        <div class="panel-body form-padding-right">

                            {!! Former::text('ticket_number_start')
                                     ->label(trans('texts.counter'))
                                    ->help('ticket_number_start_help')
                                    !!}

                            <div id="">
                                {!! Former::select('default_priority')
                                    ->text(trans('texts.default_priority'))
                                    ->options([
                                    TICKET_PRIORITY_LOW => trans('texts.low'),
                                    TICKET_PRIORITY_MEDIUM => trans('texts.medium'),
                                    TICKET_PRIORITY_HIGH => trans('texts.high'),
                                ])
                                 !!}
                            </div>

                            <div id="">
                                {!! Former::select('ticket_master_id')
                                    ->label(trans('texts.ticket_master'))
                                    ->text(trans('texts.ticket_master'))
                                    ->help(trans('texts.ticket_master_help'))
                                    ->fromQuery($account->users, 'displayName', 'id')
                                 !!}
                            </div>
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane" id="domain" >
                            <div class="panel-body form-padding-right" >

                                <div class="alert alert-danger" role="alert" id="local_part_unavailable">
                                    {!! trans('texts.local_part_unavailable')  !!}
                                </div>

                                <div class="alert alert-success" role="alert" id="local_part_available">
                                    {!! trans('texts.local_part_available')  !!}
                                </div>

                                {!! Former::text('support_email_local_part')
                                        ->placeholder('texts.local_part_placeholder')
                                        ->label(trans('texts.local_part'))
                                        ->append(Button::info(trans('texts.search'))->withAttributes(['onclick' => 'checkSupportEmail()']))
                                        ->help('texts.local_part_help') !!}


                                {!! Former::text('from_name')
                                        ->placeholder('texts.from_name_placeholder')
                                        ->label(trans('texts.from_name'))
                                        ->help('texts.from_name_help')!!}

                            </div>

                    </div>

                    <div role="tabpanel" class="tab-pane" id="attachments" >
                        <div class="panel-body form-padding-right" >

                            {!! Former::checkbox('client_upload')
                                ->text(trans('texts.enable'))
                                ->help(trans('texts.enable_client_upload_help'))
                                ->label(trans('texts.client_upload'))
                                ->value(1) !!}

                            <div id="max_file_size">
                                {!! Former::select('max_file_size')
                                    ->text(trans('texts.max_file_size'))
                                    ->fromQuery($account_ticket_settings->max_file_sizes())
                                ->help(trans('texts.max_file_size_help'))
                                 !!}
                            </div>


                            {!! Former::text('mime_types')
                                ->placeholder('texts.mime_types_placeholder')
                                ->label(trans('texts.mime_types'))
                                ->help('mime_types_help') !!}

                        </div>

                    </div>


                    <div role="tabpanel" class="tab-pane" id="notifications" >
                        <div class="panel-body form-padding-right" >

                            <div id="">
                                {!! Former::select('new_ticket_template_id')
                                    ->text(trans('texts.new_ticket_template_id'))
                                    ->addOption('','0')
                                    ->fromQuery($templates, "name", "id")
                                ->help(trans('texts.new_ticket_autoresponder_help'))
                                 !!}
                            </div>

                            <div id="">
                                {!! Former::select('update_ticket_template_id')
                                    ->text(trans('texts.update_ticket_template_id'))
                                    ->addOption('','0')
                                    ->fromQuery($templates, "name", "id")
                                ->help(trans('texts.update_ticket_autoresponder_help'))
                                 !!}
                            </div>

                            <div id="">
                                {!! Former::select('close_ticket_template_id')
                                    ->text(trans('texts.close_ticket_template_id'))
                                    ->addOption('','0')
                                    ->fromQuery($templates, "name", "id")
                                ->help(trans('texts.close_ticket_autoresponder_help'))
                                 !!}
                            </div>

                            <div id="">
                                {!! Former::select('alert_new_comment')
                                    ->text(trans('texts.alert_new_comment'))
                                    ->addOption('','0')
                                    ->fromQuery($templates, "name", "id")
                                ->help(trans('texts.alert_comment_ticket_help'))
                                 !!}
                            </div>

                            {!! Former::text('alert_new_comment_email')
                                ->placeholder('texts.comma_separated_values')
                                ->label(trans('texts.update_ticket_notification_list'))
                                ->help('alert_comment_ticket_email_help') !!}

                            <div id="">
                                {!! Former::select('alert_ticket_assign_agent')
                                    ->text(trans('texts.alert_ticket_assign_agent'))
                                    ->addOption('','0')
                                    ->fromQuery($templates, "name", "id")
                                ->help(trans('texts.alert_ticket_assign_agent_hel'))
                                 !!}
                            </div>

                            {!! Former::text('alert_ticket_assign_email')
                                ->placeholder('texts.comma_separated_values')
                                ->label(trans('texts.alert_ticket_assign_agent_notifications'))
                                ->help('alert_ticket_assign_agent_help') !!}

                            <div id="">
                                {!! Former::select('alert_ticket_overdue_agent')
                                    ->text(trans('texts.alert_ticket_overdue_agent'))
                                    ->addOption('','0')
                                    ->fromQuery($templates, "name", "id")
                                ->help(trans('texts.alert_ticket_overdue_agent_help'))
                                 !!}
                            </div>

                            {!! Former::text('alert_ticket_overdue_email')
                                ->placeholder('texts.comma_separated_values')
                                ->label(trans('texts.alert_ticket_overdue_email'))
                                ->help('alert_ticket_overdue_email_help') !!}

                            {!! Former::checkbox('show_agent_details')
                               ->text(trans('texts.enable'))
                               ->label(trans('texts.show_agent_details'))
                               ->value(1) !!}

                        </div>
                    </div>

                    {!! Former::close() !!}

                    <div role="tabpanel" class="tab-pane" id="templates" >
                        <div class="panel-body form-padding-right" >
                            {!! Button::primary(trans('texts.add_template'))
                                ->asLinkTo(URL::to('/ticket_template/create'))
                                ->withAttributes(['class' => 'pull-right'])
                                ->appendIcon(Icon::create('plus-sign')) !!}

                            @include('partials.bulk_form', ['entityType' => ENTITY_TICKET_TEMPLATE])

                            {!! Datatable::table()
                              ->addColumn(
                                trans('texts.name'),
                                trans('texts.description'),
                                trans('texts.action'))
                              ->setUrl(url('api/ticket_templates/'))
                              ->setOptions('sPaginationType', 'bootstrap')
                              ->setOptions('bFilter', false)
                              ->setOptions('bAutoWidth', false)
                              ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[1]]])
                              ->render('datatable') !!}

                        </div>
                    </div>

                </div>

            </div>

            <center>
                {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id'=>'saveButton']) !!}
            </center>

        </div>

    </div>

<script>
    window.onDatatableReady = actionListHandler;

    $( function() {

        $('#local_part_unavailable').hide();
        $('#local_part_available').hide();

    });

    function checkSupportEmail()
    {
        $.ajax({
            type: "POST",
            url : "/api/tickets/checkSupportLocalPart",
            data: { support_email_local_part: $('#support_email_local_part').val() },
            success: function(msg){

                if(msg == '{{ RESULT_SUCCESS }}') {
                    $('#local_part_available').fadeOut();
                     $('#local_part_unavailable').fadeIn();
                }
                else {
                    $('#local_part_unavailable').fadeOut();
                    $('#local_part_available').fadeIn();
                }
            }


        });
    }


</script>
@stop
