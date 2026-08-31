{{ $title }}
@isset($report_date)
{{ $report_date }}
@endisset

@isset($greeting)
{{ $greeting }}

@endisset
@isset($intro)
{{ $intro }}

@endisset
@if(! empty($summary))
@foreach($summary as $item)
{{ data_get($item, 'label') }}: {{ data_get($item, 'value') }}
@endforeach

@endif
@foreach($sections as $section)
@php($tasks = data_get($section, 'tasks', []))
@continue(empty($tasks))
{{ data_get($section, 'title') }} ({{ data_get($section, 'count', count($tasks)) }})

@foreach($tasks as $task)
- {{ data_get($task, 'title') }}
@if(data_get($task, 'context'))
  {{ data_get($task, 'context') }}
@endif
@if(! empty(data_get($task, 'metadata', [])))
  {{ implode(' | ', data_get($task, 'metadata')) }}
@endif
@if(data_get($task, 'url'))
  {{ data_get($task, 'url') }}
@endif

@endforeach
@if(data_get($section, 'remaining_text'))
{{ data_get($section, 'remaining_text') }}

@endif
@endforeach
@if($url)
{{ $button }}: {{ $url }}
@endif

@isset($signature)
{{ strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $signature)) }}
@endisset

@isset($whitelabel)
@if(! $whitelabel)
{{ ctrans('texts.ninja_email_footer', ['site' => 'https://invoiceninja.com']) }}
@endif
@endisset
