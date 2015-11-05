<div role="tabpanel" class="tab-pane {{ isset($active) && $active ? 'active' : '' }}" id="{{ $field }}">
    <div class="panel-body" style="padding-bottom: 0px">
        @if (isset($isReminder) && $isReminder)
            <div class="row">
                <div class="col-md-6">
                    {!! Former::checkbox('enable_' . $field)
                            ->text(trans('texts.enable'))->label('') !!}
                    {!! Former::input('num_days_' . $field)
                            ->label(trans('texts.num_days_reminder'))
                            ->addClass('enable-' . $field) !!}
                </div>
            </div>
        @endif
        <div class="row">
            <div class="col-md-6">
                <div class="pull-right"><a href="#" onclick="return resetText('{{ 'subject' }}', '{{ $field }}')">{{ trans("texts.reset") }}</a></div>
                {!! Former::text('email_subject_' . $field)
                        ->label(trans('texts.subject'))
                        ->addClass('enable-' . $field) !!}
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
                        ->addClass('enable-' . $field)
                        ->style('display:none') !!}
                <div id="{{ $field }}Editor" class="form-control enable-{{ $field }}" style="min-height:160px">
                </div>
            </div>
            <div class="col-md-6">
                <p>&nbsp;<p/>
                <div id="{{ $field }}_template_preview"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <p>&nbsp;<p/>
                @include('partials/quill_toolbar', ['name' => $field])
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
        });
    </script>