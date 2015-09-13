<li style="margin-top: 4px; margin-bottom: 4px; min-width: 220px; cursor: pointer">
    @if (isset($user_id) && $user_id != Auth::user()->id)
        <a href="{{ URL::to("/switch_account/{$user_id}") }}">
    @else 
        <a href="{{ URL::to("/company/details") }}">
    @endif

        @if (file_exists($logo_path))
            <div class="pull-left" style="height: 40px; margin-right: 16px;">
                <img style="width: 40px; margin-top:6px" src="{{ asset($logo_path) }}"/>
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