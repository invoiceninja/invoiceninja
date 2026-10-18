@php
    $tone_styles = [
        'critical' => ['accent' => '#dc2626', 'background' => '#fef2f2', 'text' => '#991b1b'],
        'warning' => ['accent' => '#d97706', 'background' => '#fffbeb', 'text' => '#92400e'],
        'info' => ['accent' => '#2563eb', 'background' => '#eff6ff', 'text' => '#1e40af'],
        'neutral' => ['accent' => '#64748b', 'background' => '#f8fafc', 'text' => '#334155'],
    ];
@endphp

@component('email.template.admin', ['design' => 'light', 'settings' => $settings, 'logo' => $logo, 'url' => $url])
    @isset($preheader)
        <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent; mso-hide: all;">
            {{ $preheader }}
        </div>
    @endisset

    <div style="text-align: left;">
        @isset($greeting)
            <p style="margin: 0 0 12px; font-size: 16px; line-height: 24px; color: #475569;">{{ $greeting }}</p>
        @endisset

        <h1 style="margin: 0 0 6px; text-align: left;">{{ $title }}</h1>

        @isset($report_date)
            <p style="margin: 0 0 20px; font-size: 13px; line-height: 20px; color: #64748b;">{{ $report_date }}</p>
        @endisset

        @isset($intro)
            <p style="margin: 0 0 24px; font-size: 16px; line-height: 24px; color: #475569;">{{ $intro }}</p>
        @endisset

        @if(! empty($summary))
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 0 28px; border-collapse: separate; border-spacing: 6px 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                <tr>
                    @foreach($summary as $item)
                        @php
                            $summary_style = $tone_styles[data_get($item, 'tone', 'neutral')] ?? $tone_styles['neutral'];
                        @endphp
                        <td class="dark-bg" width="{{ 100 / count($summary) }}%" valign="top" style="padding: 14px 8px; border-top: 3px solid {{ $summary_style['accent'] }}; border-radius: 4px; background-color: {{ $summary_style['background'] }}; text-align: center;">
                            <div style="font-size: 24px; line-height: 28px; font-weight: 700; color: {{ $summary_style['text'] }};">{{ data_get($item, 'value') }}</div>
                            <div style="margin-top: 4px; font-size: 12px; line-height: 16px; color: #64748b;">{{ data_get($item, 'label') }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif

        @foreach($sections as $section)
            @php
                $tasks = data_get($section, 'tasks', []);
                $section_style = $tone_styles[data_get($section, 'tone', 'neutral')] ?? $tone_styles['neutral'];
            @endphp

            @continue(empty($tasks))

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 0 24px; border-collapse: collapse; border: 1px solid #e2e8f0; border-left: 4px solid {{ $section_style['accent'] }}; border-radius: 4px; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                <tr>
                    <td class="dark-bg" style="padding: 12px 16px; background-color: {{ $section_style['background'] }}; border-bottom: 1px solid #e2e8f0;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size: 16px; line-height: 22px; font-weight: 700; color: {{ $section_style['text'] }};">
                                    {{ data_get($section, 'title') }}
                                </td>
                                <td align="right" style="font-size: 12px; line-height: 18px; color: {{ $section_style['text'] }};">
                                    {{ data_get($section, 'count', count($tasks)) }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                @foreach($tasks as $task)
                    <tr>
                        <td style="padding: 14px 16px; border-bottom: {{ $loop->last && ! data_get($section, 'remaining_text') ? '0' : '1px solid #e2e8f0' }};">
                            @if(data_get($task, 'url'))
                                <a href="{{ data_get($task, 'url') }}" target="_blank" style="font-size: 15px; line-height: 21px; font-weight: 600; color: {{ $settings->primary_color }}; text-decoration: none;">
                                    {{ data_get($task, 'title') }}
                                </a>
                            @else
                                <span style="font-size: 15px; line-height: 21px; font-weight: 600; color: #0f172a;">{{ data_get($task, 'title') }}</span>
                            @endif

                            @if(data_get($task, 'context'))
                                <div style="margin-top: 4px; font-size: 13px; line-height: 18px; color: #475569;">{{ data_get($task, 'context') }}</div>
                            @endif

                            @if(! empty(data_get($task, 'metadata', [])))
                                <div style="margin-top: 6px; font-size: 12px; line-height: 18px; color: #64748b;">
                                    {{ implode(' · ', data_get($task, 'metadata')) }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if(data_get($section, 'remaining_text'))
                    <tr>
                        <td class="dark-bg" style="padding: 10px 16px; background-color: #f8fafc; font-size: 12px; line-height: 18px; color: #64748b; text-align: center;">
                            {{ data_get($section, 'remaining_text') }}
                        </td>
                    </tr>
                @endif
            </table>
        @endforeach

        @if($url)
            <table role="presentation" align="center" border="0" cellpadding="0" cellspacing="0" style="margin: 8px auto 28px;">
                <tr>
                    <td align="center" class="new_button" style="border-radius: 3px; background-color: {{ $settings->primary_color }};">
                        <a href="{{ $url }}" target="_blank" style="display: inline-block; padding: 14px 28px; border: 1px solid {{ $settings->primary_color }}; border-radius: 3px; color: #ffffff; font-size: 16px; line-height: 20px; font-weight: 600; text-decoration: none;">
                            {{ $button }}
                        </a>
                    </td>
                </tr>
            </table>
        @endif

        @isset($signature)
            <div style="font-size: 14px; line-height: 21px; color: #475569;">{!! nl2br($signature) !!}</div>
        @endisset
    </div>
@endcomponent
