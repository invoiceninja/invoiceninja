<li style="margin-top: 4px; margin-bottom: 4px; min-width: 220px; cursor: pointer">
    @if (isset($user_id) && $show_remove)
        <a href='{{ URL::to("/switch_account/{$user_id}") }}'>
    @else 
        <a href='#' onclick="return false;">
    @endif

        @if (isset($show_remove) && $show_remove)
            <div class="pull-right glyphicon glyphicon-remove remove" onclick="return showUnlink({{ $user_account_id }}, {{ $user_id }})"></div>
        @endif

        @if (file_exists('logo/'.$account_key.'.jpg'))
            <img class="pull-left" style="width: 40px; min-height: 40px; margin-right: 16px" src="{{ asset('logo/'.$account_key.'.jpg') }}"/>
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