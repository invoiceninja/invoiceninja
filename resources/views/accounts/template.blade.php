<div role="tabpanel" class="tab-pane {{ isset($active) && $active ? 'active' : '' }}" id="{{ $field }}">
    <div class="panel-body" style="padding-bottom: 0px">
        @if (isset($isReminder) && $isReminder)

            {!! Former::populateField('enable_' . $field, intval($account->{'enable_' . $field})) !!}

            <div class="row well" style="padding-bottom:20px">
                <div class="col-md-4" style="padding-top:10px">
                    {!! Former::checkbox('enable_' . $field)
                            ->text(trans('texts.send_automatically'))->label('')
                            ->value(1) !!}
                </div>
                <div class="col-md-8">
                    {!! Former::plaintext('')
                            ->value(
                                Former::input('num_days_' . $field)
                                    ->addClass('enable-' . $field)
                                    ->style('float:left;width:20%')
                                    ->raw() .
                                Former::select('direction_' . $field)
                                    ->addOption(trans('texts.days_before'), REMINDER_DIRECTION_BEFORE)
                                    ->addOption(trans('texts.days_after'), REMINDER_DIRECTION_AFTER)
                                    ->addClass('enable-' . $field)
                                    ->style('float:left;width:40%')
                                    ->raw() .
                                '<div id="days_after_'. $field .'" style="float:left;width:40%;display:none;padding-top:8px;padding-left:16px;font-size:16px;">' . trans('texts.days_after') . '</div>' .
                                Former::select('field_' . $field)
                                    ->addOption(trans('texts.field_due_date'), REMINDER_FIELD_DUE_DATE)
                                    ->addOption(trans('texts.field_invoice_date'), REMINDER_FIELD_INVOICE_DATE)
                                    ->addClass('enable-' . $field)
                                    ->style('float:left;width:40%')
                                    ->raw()
                            ) !!}
                </div>
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
            <div class="col-md-9 show-when-ready" style="display:none">
                @include('partials/quill_toolbar', ['name' => $field])
            </div>
            <div class="col-md-3 pull-right" style="padding-top:10px;text-align:right">
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
