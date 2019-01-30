@extends('payments.payment_method')

@section('head')
    @parent

    <script src="https://js.stripe.com/v3/"></script>
    <script type="text/javascript">
        $(function() {
            $('.payment-form').submit(function(event) {
                // https://stripe.com/docs/sources/sepa-debit
                var stripe = Stripe('{{ $accountGateway->getPublishableKey() }}');
                stripe.createSource({
                    type: 'sepa_debit',
                    sepa_debit: {
                        iban: $('#iban').val(),
                    },
                    currency: 'eur',
                    owner: {
                        name: '{{ $account->getPrimaryUser()->getFullName() }}',
                    },
                }).then(function(result) {
                    console.log('create source: result');
                    console.log(result);
                }).failure(function(result) {
                    console.log('create source: error');
                    console.log(result);
                });

                return false;
            });
        });
    </script>
@stop

@section('payment_details')

    {!! Former::open($url)
            ->autocomplete('on')
            ->addClass('payment-form')
            ->id('payment-form')
            ->rules(array(
                'iban' => 'required',
                'authorize_sepa' => 'required',
            )) !!}

    @if (Utils::isNinjaDev())
        {{ Former::populateField('iban', 'DE89370400440532013000') }}
    @endif

    {!! Former::text('iban') !!}

    {!! Former::checkbox('authorize_sepa')
            ->text(trans('texts.sepa_authorization', ['company'=>$account->getDisplayName(), 'email' => $account->work_email]))
            ->label(' ')
            ->value(1) !!}


    <br/>

    <div class="col-md-8 col-md-offset-4">

        {!! Button::normal(strtoupper(trans('texts.cancel')))->large()->asLinkTo($invitation->getLink()) !!}
        &nbsp;&nbsp;
        {!! Button::success(strtoupper(trans('texts.add_account')))
                        ->submit()
                        ->withAttributes(['id'=>'add_account_button'])
                        ->large() !!}
    </div>

    {!! Former::close() !!}



@stop
