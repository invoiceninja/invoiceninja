@if ($account->hasLogo())
    @if ($account->website)
        <a href="{{ $account->website }}" style="color: #19BB40; text-decoration: underline;">
    @endif

    <img src="{{ isset($message) ? $message->embed($account->getLogoPath()) : 'cid:' . $account->getLogoName() }}" height="50" style="height:50px; max-width:140px; margin-left: 33px; padding-top: 2px" alt=""/>

    @if ($account->website)
        </a>
    @endif
@endif
