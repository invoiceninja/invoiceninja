{{ Former::populateField('notify_sent', intval(Auth::user()->notify_sent)) }}
{{ Former::populateField('notify_viewed', intval(Auth::user()->notify_viewed)) }}
{{ Former::populateField('notify_paid', intval(Auth::user()->notify_paid)) }}
{{ Former::populateField('notify_approved', intval(Auth::user()->notify_approved)) }}
{{ Former::populateField('only_notify_owned', intval(Auth::user()->only_notify_owned)) }}

<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.email_notifications') !!}</h3>
    </div>
    <div class="panel-body">
        {!! Former::checkbox('notify_sent')->label('&nbsp;')->text(trans('texts.email_sent'))->value(1) !!}
        {!! Former::checkbox('notify_viewed')->label('&nbsp;')->text(trans('texts.email_viewed'))->value(1) !!}
        {!! Former::checkbox('notify_paid')->label('&nbsp;')->text(trans('texts.email_paid'))->value(1) !!}
        {!! Former::checkbox('notify_approved')->label('&nbsp;')->text(trans('texts.email_approved'))->value(1) !!}

        @if (Auth()->user()->account->hasMultipleUsers())
            <br/>
            {!! Former::radios('only_notify_owned')->radios([
                    trans('texts.all_invoices') => array('name' => 'only_notify_owned', 'value' => 0),
                    trans('texts.my_invoices') => array('name' => 'only_notify_owned', 'value' => 1),
                ])->inline()
                    ->label('send_notifications_for') !!}
        @endif
    </div>
</div>
