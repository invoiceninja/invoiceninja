@component('email.template.admin', ['settings' => $settings, 'logo' => $logo])
    <div class="center">
        <h1>{{ $title }}</h1>

        {{ ctrans("texts.{$body}") }}

        @isset($view_link)

            <table border="0" cellspacing="0" cellpadding="0" align="center">
                <tr style="border: 0 !important; ">
                    <td class="new_button" style="padding: 12px 18px 12px 18px; border-radius:5px;" align="center"> 
                    <a href="{{ $view_link }}" target="_blank" style="border: 0 !important;font-size: 18px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; display: inline-block;">{{ $view_text }}</a>
                    </td>
                </tr>
            </table>

        @endisset

        @isset($signature)
            <p>{{ $signature }}</p>
        @endisset
    </div>
@endcomponent