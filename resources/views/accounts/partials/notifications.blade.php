{{ Former::populateField('notify_sent', intval(Auth::user()->notify_sent)) }}
{{ Former::populateField('notify_viewed', intval(Auth::user()->notify_viewed)) }}
{{ Former::populateField('notify_paid', intval(Auth::user()->notify_paid)) }}
{{ Former::populateField('notify_approved', intval(Auth::user()->notify_approved)) }}

<div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! trans('texts.email_notifications') !!}</h3>
  </div>
    <div class="panel-body">
    {!! Former::checkbox('notify_sent')->label('&nbsp;')->text(trans('texts.email_sent')) !!}
    {!! Former::checkbox('notify_viewed')->label('&nbsp;')->text(trans('texts.email_viewed')) !!}
    {!! Former::checkbox('notify_paid')->label('&nbsp;')->text(trans('texts.email_paid')) !!}
    {!! Former::checkbox('notify_approved')->label('&nbsp;')->text(trans('texts.email_approved')) !!}
    </div>
</div>
