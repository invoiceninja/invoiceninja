@component('email.template.admin', ['settings' => $settings, 'logo' => $logo])
    <div class="center">
        <h1>{{ $title }}</h1>

        {{ ctrans("texts.{$body}") }}

        @isset($view_link)
            <a class="button" href="{{ $view_link}}" target="_blank">{{{ $view_text }}}</a>
        @endisset

        @isset($signature)
            <p>{{ $signature }}</p>
        @endisset
    </div>
@endcomponent