@extends('header')

@section('head')
	@parent

    <style type="text/css">
        label.checkbox-inline {
            padding-left: 0px;
        }

        label.checkbox-inline div {
            padding-left: 20px;
        }
    </style>
@stop

@section('content')
    @parent

    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])

    {!! Former::open($url)->method($method)->rules(array(
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required',
                'company_name' => 'required',
                'tos_agree' => 'required',
                'country' => 'required',
            ))->addClass('warn-on-exit') !!}

    {!! Former::populateField('company_name', $account->getDisplayName()) !!}

    @if ($account->country)
        {!! Former::populateField('country', $account->country->iso_3166_2) !!}
    @endif

    {!! Former::populateField('first_name', $user->first_name) !!}
    {!! Former::populateField('last_name', $user->last_name) !!}
    {!! Former::populateField('email', $user->email) !!}
    {!! Former::populateField('show_address', 1) !!}
    {!! Former::populateField('update_address', 1) !!}
    {!! Former::populateField('country', 'US') !!}

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.online_payments') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">
            {!! Former::text('first_name') !!}
            {!! Former::text('last_name') !!}
            {!! Former::text('email') !!}
            {!! Former::text('company_name')->help('wepay_company_name_help')->maxlength(255) !!}

            <div id="wepay-country">
                {!! Former::radios('country')
                        ->radios([
                            trans('texts.country_United States') => ['value' => 'US'],
                            trans('texts.country_Canada') => ['value' => 'CA'],
                            trans('texts.country_United Kingdom') => ['value' => 'GB'],
                        ]) !!}
            </div>
            <div id="wepay-accept-debit" style="display:none">
                {!! Former::checkbox('debit_cards')
                        ->text(trans('texts.accept_debit_cards'))
                        ->value(1) !!}

            </div>

            {!! Former::checkbox('show_address')
                ->label(trans('texts.billing_address'))
                ->text(trans('texts.show_address_help'))
                ->value(1) !!}
            {!! Former::checkbox('update_address')
                    ->label(' ')
                    ->text(trans('texts.update_address_help'))
                    ->value(1) !!}
            {!! Former::checkboxes('creditCardTypes[]')
                    ->label('accepted_card_logos')
                    ->checkboxes($creditCardTypes)
                    ->class('creditcard-types')
                    ->inline()
                    ->value(1) !!}
            {!! Former::checkbox('enable_ach')
                    ->label(trans('texts.ach'))
                    ->text(trans('texts.enable_ach'))
                    ->value(1) !!}

			{!! Former::checkbox('tos_agree')->label(' ')
				->text(trans('texts.wepay_payment_tos_agree', [
					'terms' => '<a href="https://go.wepay.com/terms-of-service" target="_blank">'.trans('texts.terms_of_service').'</a>',
					'privacy_policy' => '<a href="https://go.wepay.com/privacy-policy" target="_blank">'.trans('texts.privacy_policy').'</a>',
				]))
				->value('true')
				->inlineHelp('standard_fees_apply') !!}

          </div>

          <center>
          <table id="canadaFees" width="80%" style="border: solid 1px black;display:none;margin-bottom:40px">
              <tr style="border: solid 1px black">
                  <th colspan="2" style="text-align:center;padding: 4px">
                      Fees Disclosure Box
                  </th>
              </tr>
              <tr style="border: solid 1px black;vertical-align:top">
                  <td style="border-left: solid 1px black; padding: 8px">
                      <h4>Payment Card Type</h4>
                      (These are the most common domestically issued card types
                      and processing methods. They do not represent all the
                      possible fees and variations that are charged to the
                      merchants.)
                  </td>
                  <td style="padding: 8px">
                      <h4>Processing Method: Card Not Present</h4>
                      (Means that the card/device was not
                      electronically read. Generally, the card
                      information is manually key-entered, e.g. online
                      payment)
                  </td>
              </tr>
              @foreach ([
                  'Visa Consumer Credit',
                  'Visa Infinite',
                  'Visa Infinite Privilege',
                  'Visa Business',
                  'Visa Business Premium',
                  'Visa Corporate',
                  'Visa Prepaid',
                  'Visa Debit',
                  'MasterCard Consumer Credit',
                  'MasterCard World',
                  'MasterCard World Elite',
                  'MasterCard Business/Corporate',
                  'MasterCard Debit',
                  'MasterCard Prepaid',
                  'American Express',
              ] as $type)
                  <tr>
                      <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                          {{ $type }}
                      </td>
                      <td style="text-align:center">
                          2.9% + CA$0.30
                      </td>
                  </tr>
              @endforeach
              <tr style="border: solid 1px black;">
                  <th colspan="2" style="text-align:center;padding: 4px">
                      Other Fees Disclosure Box
                  </td>
              </tr>
              <tr style="border: solid 1px black;">
                  <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                      Chargeback
                  </td>
                  <td style="text-align:center">
                      CA$15.00
                  </td>
              </tr>
          </table>
          </center>
        </div>

        <br/>
        <center>
            {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/gateways/create'))->appendIcon(Icon::create('remove-circle')) !!}
            {!! Button::success(trans('texts.sign_up_with_wepay'))->submit()->large() !!}
        </center>


    <style>
        #other-providers{display:none}
        #wepay-country .radio{display:inline-block;padding-right:15px}
        #wepay-country .radio label{padding-left:0}
    </style>

    <script type="text/javascript">
        $(function(){
            $('#wepay-country input').change(handleCountryChange)
            function handleCountryChange(){
                var country = $('#wepay-country input:checked').val();
                if (country) {
                    $('#wepay-accept-debit').toggle(country == 'CA');
                    $('#canadaFees').toggle(country == 'CA');
                }
            }
            handleCountryChange();
        })
    </script>

    <input type="hidden" name="primary_gateway_id" value="{{ GATEWAY_WEPAY }}">
    {!! Former::close() !!}

@stop
