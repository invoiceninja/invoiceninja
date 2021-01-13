@if ($company->present()->logo($settings))
    @if ($settings->website)
        <a href="{{ $settings->website }}" style="color: #19BB40; text-decoration: underline;">
    @endif

    <img src="{{ $company->present()->logo($settings) }}" height="50" style="height:50px; max-width:140px; margin-left: 33px; padding-top: 2px" alt=""/>

    @if ($settings->website)
        </a>
    @endif
@endif
