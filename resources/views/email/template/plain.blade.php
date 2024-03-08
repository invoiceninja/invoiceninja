<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title></title>
</head>

<div id="content-wrapper">
    {!! $body !!}
</div>

@if($signature)
    <tr>
        <td>
            <p>{!! $signature !!}</p>
        </td>
    </tr>
@endif

@isset($links)

    @if(count($links) >=1)
    <p><strong>{{ ctrans('texts.attachments') }}</strong></p>
    @endif

    @foreach($links as $link)
        <tr>
            <td>
                <p> {!! $link ?? '' !!}</p>
            </td>
        </tr>
    @endforeach
@endisset

@isset($whitelabel)
    @if(!$whitelabel)
        <p>
            <a href="https://invoiceninja.com" target="_blank">
                {{ __('texts.ninja_email_footer', ['site' => 'Invoice Ninja']) }}
            </a>
        </p>
    @endif
@endisset

@if(isset($email_preferences) && $email_preferences)
<p><a href="{!! $email_preferences !!}">{{ ctrans('texts.email_preferences') }}</a></p>
@endif