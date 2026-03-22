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

            @isset($table)
            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333333;">
                <thead>
                    <tr>
                        @foreach($table_headers as $key => $value)
                            <th align="left" valign="middle" style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057; text-align: left;">{{ $value }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($table as $index => $row)
                        <tr style="background-color: {{ $index % 2 === 0 ? '#ffffff' : '#f8f9fa' }};">
                            @foreach($row as $key => $value)
                                <td align="left" valign="middle" style="padding: 10px 16px; border-bottom: 1px solid #e9ecef; color: #212529;">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
