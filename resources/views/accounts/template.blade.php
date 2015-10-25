<div role="tabpanel" class="tab-pane {{ isset($active) && $active ? 'active' : '' }}" id="{{ $field }}">
    <div class="panel-body">
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
                {!! Former::text('email_subject_' . $field)
                        ->label(trans('texts.subject'))
                        ->addClass('enable-' . $field) !!}
                <div class="pull-right"><a href="#" onclick="return resetText('{{ 'subject' }}', '{{ $field }}')">{{ trans("texts.reset") }}</a></div>
            </div>
        <div class="col-md-6">
            <p>&nbsp;<p/>
                <div id="{{ $field }}_subject_preview"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                {!! Former::textarea('email_template_' . $field)
                        ->label(trans('texts.body'))
                        ->addClass('enable-' . $field) !!}
                <div class="pull-right"><a href="#" onclick="return resetText('{{ 'template' }}', '{{ $field }}')">{{ trans("texts.reset") }}</a></div>
            </div>
            <div class="col-md-6">
                <p>&nbsp;<p/>
                <div id="{{ $field }}_template_preview"></div>
            </div>
        </div>
    </div>
</div>