@component('email.template.admin', ['design' => 'light', 'settings' => $settings, 'logo' => $logo, 'url' => $url])
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
            @isset($content)
                {!! nl2br($content, true) !!}
            @endisset

            @isset($slot)
                {{ $slot }}
            @endisset
        </div>

        @isset($additional_info)
            <p>{{ $additional_info }}</p>
        @endisset

        @if($url)

        <!--[if (gte mso 9)|(IE)]>
        <table align="center" cellspacing="0" cellpadding="0" style="width: 600px;">
            <tr>
            <td align="center" valign="top">
                <![endif]-->        
                <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" >
                <tbody><tr>
                <td align="center" class="new_button" style="border-radius: 2px; background-color: {{ $settings->primary_color }} ;">
                    <a href="{{ $url }}" target="_blank" class="new_button" style="text-decoration: none; border: 1px solid {{ $settings->primary_color }}; display: inline-block; border-radius: 2px; padding-top: 15px; padding-bottom: 15px; padding-left: 25px; padding-right: 25px; font-size: 20px; color: #fff">
                    <singleline label="cta button">{{ ctrans($button) }}</singleline>
                    </a>
                </td>
                </tr>
                </tbody>
                </table>
        <!--[if (gte mso 9)|(IE)]>
            </td>
            </tr>
        </table>
        <![endif]-->


        @endif

        @isset($signature)
            <p>{!! nl2br($signature) !!}</p>
        @endisset
    </div>
@endcomponent
