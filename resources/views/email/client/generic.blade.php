@component('email.template.client', ['design' => 'light', 'settings' => $settings, 'logo' => $logo, 'company' => $company ?? ''])
    <div class="center">
        @isset($greeting)
            <p>{{ $greeting }}</p>
        @endisset

        @isset($title)
            <h1>{{ $title }}</h1>
        @endisset

        @isset($h2)
            <h2>{{ $title }}</h2>
        @endisset

        <div style="margin-top: 10px; margin-bottom: 30px;">
            {{ $content }}
        </div>

        @isset($additional_info)
            <p>{{ $additional_info }}</p>
        @endisset

        @isset($url)
            <!-- <a href="{{ $url }}" class="button" target="_blank">{{ ctrans($button) }}</a> -->

            <table border="0" cellspacing="0" cellpadding="0" align="center">
                <tr style="border: 0 !important; ">
                    <td class="new_button" style="padding: 12px 18px 12px 18px; border-radius:5px;" align="center"> 
                    <a href="{{ $url }}") }}" target="_blank" style="border: 0 !important;font-size: 18px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; display: inline-block;">
                      {{ ctrans($button) }}           
                    </a>
                    </td>
                </tr>
            </table>

        @endisset

        @isset($signature)
            <p>{{ nl2br($signature) }}</p>
        @endisset
    </div>
@endcomponent
