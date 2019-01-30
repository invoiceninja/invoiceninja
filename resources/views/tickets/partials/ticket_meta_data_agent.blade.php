<table width="100%">
    <tr>
        <td width="50%" style="vertical-align:top;">
            <table class="table table-striped datatable">
                <tbody>
                <tr><td class="td-left">{!! trans('texts.ticket_number')!!}</td><td class="td-right">{!! $ticket->ticket_number !!}</td></tr>
                <tr><td class="td-left">{!! trans('texts.category') !!}:</td><td class="td-right">{!! $ticket->category->name !!}</td></tr>
                <tr><td class="td-left">{!! trans('texts.subject')!!}:</td><td class="td-right">{!! substr($ticket->subject, 0, 30) !!}</td></tr>
                @if($ticket->client)
                    <tr><td class="td-left" style="height:60px">{!! trans('texts.client') !!}:</td><td class="td-right">{!! $ticket->client->name !!}</td></tr>
                @else
                    <tr><td class="td-left" style="height:60px">{!! trans('texts.client') !!}:</td><td class="td-right"></td></tr>
                @endif

                @if(count($ticket->child_tickets) > 0)
                    <tr><td class="td-left">{!! trans('texts.linked_tickets')!!}</td><td class="td-right">
                            @foreach($ticket->child_tickets as $child)
                                {!!  link_to("tickets/{$child->public_id}", $child->public_id ?: '')->toHtml() !!}
                            @endforeach
                        </td></tr>
                @elseif($ticket->getContactName())
                    <tr><td class="td-left" style="height:77px">{!! trans('texts.contact') !!}:</td><td class="td-right">{!! $ticket->getContactName() !!}</td></tr>
                @elseif($ticket->parent_ticket_id)
                    <tr><td class="td-left">{!! trans('texts.parent_ticket')!!}</td><td class="td-right">
                            {!!  link_to("tickets/{$ticket->parent_ticket->public_id}", $ticket->parent_ticket->public_id ?: '')->toHtml() !!}
                        </td></tr>
                @endif
                <tr><td class="td-left">{!! trans('texts.assigned_to') !!}:</td><td class="td-right">
                        @if($ticket->agent)
                            {!! $ticket->agent->getName() !!} {!! Icon::create('random') !!}
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
                        <input id="due_date" type="text" data-bind="value: due_date.pretty, enable: isAdminUser" name="due_date"
                               class="form-control time-input time-input-end" placeholder="{{ trans('texts.due_date') }}"/>
                    </td>
                </tr>
                <tr><td class="td-left">{!! trans('texts.priority') !!}:</td><td class="td-right">{!! $ticket->getPriorityName() !!}</td></tr>

                @if($ticket->merged_parent_ticket_id)
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