@extends('header')

@section('content')
  @parent
  @include('accounts.nav', ['selected' => ACCOUNT_USER_MANAGEMENT])

  {!! Former::open($url)->autocomplete('off')->method($method)->addClass('warn-on-exit user-form')->rules(array(
      'first_name' => 'required',
      'last_name' => 'required',
      'email' => 'required|email',
  )); !!}

  @if ($user)
    {!! Former::populate($user) !!}
    {{ Former::populateField('is_admin', intval($user->is_admin)) }}
  @endif

  <div style="display:none">
      {!! Former::text('action') !!}
  </div>

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
      <script type="text/javascript">
          $(function() {
              $('input[type=checkbox]').prop('disabled', true);
          })
      </script>
    @endif

  {!! Former::checkbox('is_admin')
      ->label('&nbsp;')
      ->value(1)
      ->text(trans('texts.administrator'))
      ->help(trans('texts.administrator_help')) !!}

  @foreach (json_decode(PERMISSION_ENTITIES,1) as $permissionEntity)
  {!! Former::checkboxes('permissions[]')
      ->label(ucfirst($permissionEntity))
      ->checkboxes([
      trans('texts.create') => ['id'=> 'permissions[create_' . $permissionEntity . ']', 'name' => 'permissions[create_' . $permissionEntity . ']', 'value' => 'create_' . $permissionEntity . '', is_array(json_decode($user->permissionsV2,1)) && in_array('create_' . $permissionEntity, json_decode($user->permissionsV2,1), FALSE) ? 'checked' : '' ],
      trans('texts.view') => ['id'=> 'permissions[view_' . $permissionEntity . ']', 'name' => 'permissions[view_' . $permissionEntity . ']', 'value' => 'view_' . $permissionEntity . '', is_array(json_decode($user->permissionsV2,1)) && in_array('view_' . $permissionEntity, json_decode($user->permissionsV2,1), FALSE) ? 'checked' : ''],
      trans('texts.edit') => ['id'=> 'permissions[edit_' . $permissionEntity . ']', 'name' => 'permissions[edit_' . $permissionEntity . ']', 'value' => 'edit_' . $permissionEntity . '', is_array(json_decode($user->permissionsV2,1)) && in_array('edit_' . $permissionEntity, json_decode($user->permissionsV2,1), FALSE) ? 'checked' : ''],
      ]) !!}
  @endforeach

</div>
</div>

  <center class="buttons">
      {!! Button::normal(trans('texts.cancel'))->asLinkTo(URL::to('/settings/user_management'))->appendIcon(Icon::create('remove-circle'))->large() !!}
      {!! ($user) ? Button::success(trans('texts.save'))->withAttributes(['onclick' => 'submitAction("save")'])->large()->appendIcon(Icon::create('floppy-disk')) : false !!}
      {!! (! $user || ! $user->confirmed) ? Button::info(trans($user ? 'texts.resend_invite' : 'texts.send_invite'))->withAttributes(['onclick' => 'submitAction("email")'])->large()->appendIcon(Icon::create('send')) : false !!}
  </center>

  {!! Former::close() !!}

  <script type="text/javascript">

      function submitAction(value) {
          $('#action').val(value);
          $('.user-form').submit();
      }

  </script>

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
