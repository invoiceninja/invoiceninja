<div class="modal fade" id="emailModal" tabindex="-1" role="dialog" aria-labelledby="emailModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="min-width:150px">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="emailModalLabel">{{ trans('texts.email_invoice') }}</h4>
            </div>

            <div class="modal-body">
            <div class="panel-body">

                {!! Former::plaintext('recipients')
                        ->value('') !!}

                {!! Former::select('template')
                        ->options([
                            'initial' => trans('texts.initial_email'),
                            'reminder1' => trans('texts.first_reminder'),
                            'reminder2' => trans('texts.second_reminder'),
                            'reminder3' => trans('texts.third_reminder'),
                        ]) !!}

                <br/>
                <div role="tabpanel">
                    <ul class="nav nav-tabs" role="tablist" style="border: none">
                        <li role="presentation" class="active">
                            <a href="#preview" aria-controls="preview" role="tab" data-toggle="tab">{{ trans('texts.preview') }}</a>
                        </li>
                        <li role="presentation">
                            <a href="#customize" aria-controls="customize" role="tab" data-toggle="tab">{{ trans('texts.customize') }}</a>
                        </li>
                        <li role="presentation">
                            <a href="#history" aria-controls="history" role="tab" data-toggle="tab">{{ trans('texts.history') }}</a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="preview">
                        <div id="emailSubject"></div>
                        <div id="emailBody"></div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="customize">

                    </div>
                    <div role="tabpanel" class="tab-pane" id="history">
                        <br/>
                        @if (count($activities = $invoice->emailHistory()))
                        <table class="table table-striped data-table">
                            <tr>
                                <th>{{ trans('texts.template')}}</th>
                                <th>{{ trans('texts.contact')}}</th>
                                <th>{{ trans('texts.date')}}</th>
                            </tr>
                            @foreach ($activities as $activity)
                            <tr>
                                <td>{{ $activity->present()->notes }}</td>
                                <td>
                                    <span title="{{ trans('texts.sent_by', ['user' => $activity->present()->user]) }}">
                                        {{ $activity->contact->getDisplayName() }}
                                    </span>
                                </td>
                                <td>
                                    <span title="{{ $activity->present()->createdAt }}">
                                        {{ $activity->present()->createdAtDate }} - {{ trans_choice('texts.days_ago', $activity->created_at->diffInDays()) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        @else
                            <center style="font-size:16px;color:#888888;padding-top:20px;">
                                {{ trans("texts.{$invoice->getEntityType()}_not_emailed") }}
                            </center>
                        @endif
                    </div>
                </div>

            </div>
            </div>

            <div class="modal-footer" style="margin-top: 0px">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
                <button type="button" class="btn btn-info" onclick="onConfirmEmailClick()">{{ trans('texts.send_email') }}</button>
            </div>

        </div>
    </div>
</div>

<script type="text/javascript">

</script>
