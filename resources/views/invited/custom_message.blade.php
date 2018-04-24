@if ($message == strip_tags($message))
    <div class="alert alert-warning custom-message">{!! nl2br(Utils::isNinja() ? HTMLUtils::sanitizeHTML($message) : $message) !!}</div>    
@else
    {!! Utils::isNinja() ? HTMLUtils::sanitizeHTML($message) : $message !!}
@endif
