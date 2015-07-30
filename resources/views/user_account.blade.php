<li style="margin-top: 4px; margin-bottom: 4px; min-width: 220px; cursor: pointer">
    @if (isset($user_id) && $show_remove)
        <a href='{{ URL::to("/switch_account/{$user_id}") }}'>
    @else 
        <a href='{{ URL::to("/company/details") }}'>
    @endif

        @if (isset($show_remove) && $show_remove)
            <div class="pull-right glyphicon glyphicon-remove remove" onclick="return showUnlink({{ $user_account_id }}, {{ $user_id }})" title="{{ trans('texts.unlink') }}"></div>
        @endif

        @if (file_exists('logo/'.$account_key.'.jpg'))
            <div class="pull-left" style="height: 40px; margin-right: 16px;">
                <img style="width: 40px; margin-top:6px" src="{{ asset('logo/'.$account_key.'.jpg') }}"/>
            </div>
        @else
            <div class="pull-left" style="width: 40px; min-height: 40px; margin-right: 16px">&nbsp;</div>
        @endif

        @if (isset($selected) && $selected)
            <b>
        @endif

        <div class="account" style="padding-right:90px">{{ $account_name }}</div>
        <div class="user" style="padding-right:90px">{{ $user_name }}</div>

        @if (isset($selected) && $selected)            
            </b>
        @endif

    </a>

</li>