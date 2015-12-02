@extends('emails.master')

@section('content')
    <tr>
        <td bgcolor="#F4F5F5" style="border-collapse: collapse;">&nbsp;</td>
    </tr>
    <tr>
        <td style="border-collapse: collapse;">
            <table cellpadding="10" cellspacing="0" border="0" bgcolor="#F4F5F5" width="600" align="center" 
                class="header" style="border-top-width: 6px; border-top-color: {{ $account->primary_color ?: '#2E2B2B' }}; border-top-style: solid;">
                <tr>
                    <td class="logo" width="208" style="border-collapse: collapse; vertical-align: middle;" valign="middle">
                        @include('emails.partials.account_logo')
                    </td>
                    <td width="183" style="border-collapse: collapse; vertical-align: middle;" valign="middle">
                        <p class="left" style="line-height: 22px; margin: 0; padding: 2px 0 0;">
                            <span style="font-size: 11px; color: #8f8d8e;">
                            @if ($invoice->due_date)
                                {{ strtoupper(trans('texts.due_by', ['date' => $account->formatDate($invoice->due_date)])) }}
                            @endif
                            </span><br />
                            <span style="font-size: 18px;">
                                {{ trans("texts.{$entityType}") }} {{ $invoice->invoice_number }}
                            </span>
                        </p>
                    </td>
                    <td style="border-collapse: collapse; vertical-align: middle;" valign="middle">
                        <p class="right" style="line-height: 14px; margin: 0; padding: 0;">
                            <span style="font-size: 15px; color: #231F20;">
                                {{ trans('texts.' . $invoice->present()->balanceDueLabel) }}:
                            </span><br />
                            <span class="total" style="font-size: 26px; display: block;margin-top: 5px;">
                                {{ $account->formatMoney($invoice->getRequestedAmount(), $client) }}
                            </span>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="content" style="border-collapse: collapse;">
            <div style="font-size: 18px; margin: 42px 40px 42px; padding: 0;">{!! $body !!}</div>
        </td>
    </tr>
@stop