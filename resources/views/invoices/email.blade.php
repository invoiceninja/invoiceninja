<div class="modal fade" id="emailModal" tabindex="-1" role="dialog" aria-labelledby="emailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
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
                        ->onchange('loadTemplate()')
                        ->options([
                            $invoice->getEntityType() => trans('texts.initial_email'),
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
                            <a href="#customize" aria-controls="customize" role="tab" data-toggle="tab">
                                {{ trans('texts.customize') }} {!! Auth::user()->isTrial() ? '<sup>' . trans('texts.pro') . '</sup>' : '' !!}
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#history" aria-controls="history" role="tab" data-toggle="tab">{{ trans('texts.history') }}</a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="preview">
                        <div style="padding:31px 14px 0px 14px">
                            <div id="emailSubjectDiv"></div>
                            <br/>
                            <div id="emailBodyDiv"></div>
                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="customize">
                        <br/>
                        {!! Former::text('emailSubject')
                                ->placeholder('subject')
                                ->onchange('onEmailSubjectChange()')
                                ->raw() !!}
                        <br/>
                        <div id="templateEditor" class="form-control" style="min-height:160px"></div>
                        <div style="displayx:none">
                            {!! Former::textarea("emailTemplate[body]")
                                    ->raw() !!}
                            {!! Former::text('emailTemplate[subject]')
                                    ->raw() !!}
                            {!! Former::text('reminder')
                                    ->raw() !!}
                        </div>
                        @include('partials/quill_toolbar', ['name' => 'template'])
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
                                        {{ $activity->present()->createdAtDate }} - {{ $activity->created_at->diffInDays() > 0 ? trans_choice('texts.days_ago', $activity->created_at->diffInDays()) : trans('texts.today') }}
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

    var emailSubjects = [];
    emailSubjects['{{ $invoice->getEntityType() }}'] = "{{ $account->getEmailSubject($invoice->getEntityType()) }}";
    emailSubjects['reminder1'] = "{{ $account->getEmailSubject('reminder1') }}";
    emailSubjects['reminder2'] = "{{ $account->getEmailSubject('reminder2') }}";
    emailSubjects['reminder3'] = "{{ $account->getEmailSubject('reminder3') }}";

    var emailTemplates = [];
    emailTemplates['{{ $invoice->getEntityType() }}'] = "{{ $account->getEmailTemplate($invoice->getEntityType()) }}";
    emailTemplates['reminder1'] = "{{ $account->getEmailTemplate('reminder1') }}";
    emailTemplates['reminder2'] = "{{ $account->getEmailTemplate('reminder2') }}";
    emailTemplates['reminder3'] = "{{ $account->getEmailTemplate('reminder3') }}";

    function showEmailModal() {
        loadTemplate();
        $('#recipients').html(getSendToEmails());
		$('#emailModal').modal('show');
    }

    function loadTemplate() {
        var template = dencodeEntities(emailSubjects[$('#template').val()]);
        $("#emailSubject").val(template);

        var template = dencodeEntities(emailTemplates[$('#template').val()]);
        emailEditor.setHTML(template);

        var reminder = $('#template').val();
        if (reminder == '{{ $invoice->getEntityType() }}') {
            reminder = '';
        }
        $('#reminder').val(reminder);

        refreshPreview();
    }

    function refreshPreview() {
        var invoice = createInvoiceModel();
        invoice = calculateAmounts(invoice);
        console.log(invoice);
        var template = $("#emailSubject").val();
        $('#emailSubjectDiv').html('<b>' + renderEmailTemplate(template, invoice) + '</b>');
        var template = emailEditor.getHTML();
        $('#emailBodyDiv').html(renderEmailTemplate(template, invoice));
    }

    function dencodeEntities(s) {
		return $("<div/>").html(s).text();
	}

    function onEmailSubjectChange() {
        $("#emailTemplate\\[subject\\]").val($('#emailSubject').val());
        refreshPreview();
        NINJA.formIsChanged = true;
    }

    var emailEditor;

    $(function() {
      emailEditor = new Quill('#templateEditor', {
          modules: {
            'toolbar': { container: '#templateToolbar' },
            'link-tooltip': true
          },
          theme: 'snow'
      });
      emailEditor.setHTML('test');
      emailEditor.on('text-change', function(delta, source) {
          if (source == 'api') {
            return;
          }
          var html = emailEditor.getHTML();
          $("#emailTemplate\\[body\\]").val(html);
          refreshPreview();
          NINJA.formIsChanged = true;
        });
    });

</script>

<style type="text/css">
    @media only screen and (min-width : 767px) {
        .modal-dialog {
            width: 660px;
        }
    }
</style>
