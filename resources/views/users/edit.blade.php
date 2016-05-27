@extends('header')

@section('content')
  @parent
  @include('accounts.nav', ['selected' => ACCOUNT_USER_MANAGEMENT])

  {!! Former::open($url)->method($method)->addClass('warn-on-exit')->rules(array(
      'first_name' => 'required',
      'last_name' => 'required',
      'email' => 'required|email',
  )); !!}

  @if ($user)
    {!! Former::populate($user) !!}
    {{ Former::populateField('is_admin', intval($user->is_admin)) }}
    {{ Former::populateField('permissions[create_all]', intval($user->hasPermission('create'))) }}
    {{ Former::populateField('permissions[view_all]', intval($user->hasPermission('view_all'))) }}
    {{ Former::populateField('permissions[edit_all]', intval($user->hasPermission('edit_all'))) }}
  @endif

<div class="panel panel-default">
<div class="panel-heading">
    <h3 class="panel-title">{!! trans('texts.user_details') !!}</h3>
</div>
<div class="panel-body form-padding-right">

  {!! Former::text('first_name') !!}
  {!! Former::text('last_name') !!}
  {!! Former::text('email') !!}

</div>
</div>

<div class="panel panel-default">
<div class="panel-heading">
    <h3 class="panel-title">{!! trans('texts.permissions') !!}</h3>
</div>
<div class="panel-body form-padding-right">

    @if ( ! Utils::hasFeature(FEATURE_USER_PERMISSIONS))
      <div class="alert alert-warning">{{ trans('texts.upgrade_for_permissions') }}</div>
      <script>
          $(function() {
              $('input[type=checkbox]').prop('disabled', true);
          })
      </script>
    @endif

  {!! Former::checkbox('is_admin')
      ->label('&nbsp;')
      ->text(trans('texts.administrator'))
      ->help(trans('texts.administrator_help')) !!}
  {!! Former::checkbox('permissions[create_all]')
      ->value('create_all')
      ->label('&nbsp;')
      ->id('permissions_create_all')
      ->text(trans('texts.user_create_all'))
      ->help(trans('texts.create_all_help')) !!}
  {!! Former::checkbox('permissions[view_all]')
      ->value('view_all')
      ->label('&nbsp;')
      ->id('permissions_view_all')
      ->text(trans('texts.user_view_all'))
      ->help(trans('texts.view_all_help')) !!}
  {!! Former::checkbox('permissions[edit_all]')
      ->value('edit_all')
      ->label('&nbsp;')
      ->id('permissions_edit_all')
      ->text(trans('texts.user_edit_all'))
      ->help(trans('texts.edit_all_help')) !!}

</div>
</div>

  {!! Former::actions(
      Button::normal(trans('texts.cancel'))->asLinkTo(URL::to('/settings/user_management'))->appendIcon(Icon::create('remove-circle'))->large(),
      Button::success(trans($user && $user->confirmed ? 'texts.save' : 'texts.send_invite'))->submit()->large()->appendIcon(Icon::create($user && $user->confirmed ? 'floppy-disk' : 'send'))
  )!!}

  {!! Former::close() !!}

@stop

@section('onReady')
    $('#first_name').focus();
	$('#is_admin, #permissions_view_all').change(fixCheckboxes);
	function fixCheckboxes(){
		var adminChecked = $('#is_admin').is(':checked');
		var viewChecked = $('#permissions_view_all').is(':checked');

		$('#permissions_view_all').prop('disabled', adminChecked);
        $('#permissions_create_all').prop('disabled', adminChecked);
        $('#permissions_edit_all').prop('disabled', adminChecked || !viewChecked);
        if(!viewChecked)$('#permissions_edit_all').prop('checked',false)
	}
	fixCheckboxes();
@stop
