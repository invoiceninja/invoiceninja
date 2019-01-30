<div role="tabpanel" class="tab-pane {{ isset($active) && $active ? 'active' : '' }}" id="{{ $field }}">
    <div class="panel-body" style="padding-bottom: 0px">
        @if (isset($isReminder) && $isReminder)

            {!! Former::populateField('enable_' . $field, intval($account->{'enable_' . $field})) !!}
            @if (floatval($fee = $account->account_email_settings->{"late_fee{$number}_amount"}))
                {!! Former::populateField('late_fee' . $number . '_amount', $fee) !!}
            @endif
            @if (floatval($fee = $account->account_email_settings->{"late_fee{$number}_percent"}))
                {!! Former::populateField('late_fee' . $number . '_percent', $fee) !!}
            @endif

            <div class="well" style="padding-bottom:20px">
                <div class="row">
                    <div class="col-md-6">
                        @if ($field == 'reminder4')
                            {!! Former::populateField('frequency_id_reminder4', $account->account_email_settings->frequency_id_reminder4) !!}
                            {!! Former::plaintext('frequency')
                                    ->value(
                                        Former::select('frequency_id_reminder4')
                                            ->options(\App\Models\Frequency::selectOptions())
                                            ->style('float:left;')
                                            ->raw()
                                    ) !!}
                        @else
                            {!! Former::plaintext('schedule')
                                    ->value(
                                        Former::input('num_days_' . $field)
                                            ->style('float:left;width:20%')
                                            ->raw() .
                                        Former::select('direction_' . $field)
                                            ->addOption(trans('texts.days_before'), REMINDER_DIRECTION_BEFORE)
                                            ->addOption(trans('texts.days_after'), REMINDER_DIRECTION_AFTER)
                                            ->style('float:left;width:40%')
                                            ->raw() .
                                        '<div id="days_after_'. $field .'" style="float:left;width:40%;display:none;padding-top:8px;padding-left:16px;font-size:16px;">' . trans('texts.days_after') . '</div>' .
                                        Former::select('field_' . $field)
                                            ->addOption(trans('texts.field_due_date'), REMINDER_FIELD_DUE_DATE)
                                            ->addOption(trans('texts.field_invoice_date'), REMINDER_FIELD_INVOICE_DATE)
                                            ->style('float:left;width:40%')
                                            ->raw()
                                    ) !!}
                        @endif
                    </div>
                    <div class="col-md-6">

                        {!! Former::checkbox('enable_' . $field)
                                ->text('enable')
                                ->label('send_email')
                                ->value(1) !!}

                    </div>
                </div>
                @if ($field != 'reminder4')
                    <div class="row" style="padding-top:30px">
                        <div class="col-md-6">
                            {!! Former::text('late_fee' . $number . '_amount')
                                            ->label('late_fee_amount')
                                            ->type('number')
                                            ->step('any') !!}
                        </div>
                        <div class="col-md-6">
                            {!! Former::text('late_fee' . $number . '_percent')
                                            ->label('late_fee_percent')
                                            ->type('number')
                                            ->step('any')
                                            ->append('%') !!}
                        </div>
                    </div>
                @endif
            </div>

            <br/>
        @endif
        <div class="row">
            <div class="col-md-6">
                <div class="pull-right"><a href="#" onclick="return resetText('{{ 'subject' }}', '{{ $field }}')">{{ trans("texts.reset") }}</a></div>
                {!! Former::text('email_subject_' . $field)
                        ->label(trans('texts.subject'))
                        ->appendIcon('question-sign')
                        ->addGroupClass('email-subject') !!}
            </div>
        <div class="col-md-6">
            <p>&nbsp;<p/>
                <div id="{{ $field }}_subject_preview"></div>
            </div>
        </div>
        <div class="row">
            <br/>
            <div class="col-md-6">
                <div class="pull-right"><a href="#" onclick="return resetText('{{ 'template' }}', '{{ $field }}')">{{ trans("texts.reset") }}</a></div>
                {!! Former::textarea('email_template_' . $field)
                        ->label(trans('texts.body'))
                        ->style('display:none') !!}
                <div id="{{ $field }}Editor" class="form-control" style="min-height:160px">
                </div>
            </div>
            <div class="col-md-6">
                <p>&nbsp;<p/>
                <div id="{{ $field }}_template_preview"></div>
            </div>
        </div>
        <p>&nbsp;<p/>
        <div class="row">
            <div class="pull-left show-when-ready" style="display:none">
                @include('partials/quill_toolbar', ['name' => $field])
            </div>
            <div class="pull-right" style="padding-top:13px;text-align:right">
                {!! Button::normal(trans('texts.raw'))->withAttributes(['onclick' => 'showRaw("'.$field.'")'])->small() !!}
                {!! Button::primary(trans('texts.preview'))->withAttributes(['onclick' => 'serverPreview("'.$field.'")'])->small() !!}
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(function() {
        var editor = new Quill('#{{ $field }}Editor', {
          modules: {
            'toolbar': { container: '#{{ $field }}Toolbar' },
            'link-tooltip': true
          },
          theme: 'snow'
        });
        editor.setHTML($('#email_template_{{ $field }}').val());
        editor.on('text-change', function(delta, source) {
              if (source == 'api') {
                return;
              }
              var html = editors['{{ $field }}'].getHTML();
              $('#email_template_{{ $field }}').val(html);
              refreshPreview();
              NINJA.formIsChanged = true;
            });
        editors['{{ $field }}'] = editor;

        $('#field_{{ $field }}').change(function() {
            setDirectionShown('{{ $field }}');
        })
        setDirectionShown('{{ $field }}');

        $('.email-subject .input-group-addon').click(function() {
            $('#templateHelpModal').modal('show');
        });
    });

</script>
