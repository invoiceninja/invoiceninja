@extends('layouts.app')

@section('content')
    <h2>Pagamento processado!</h2>
    <p>Fatura #{{ $invoice->number }} foi registrada.</p>
    <p>Status atual: <strong>{{ $invoice->present()->status }}</strong></p>
    @if($invoice->custom_value3)
        <p><a href="{{ $invoice->custom_value3 }}" target="_blank">Ver comprovante</a></p>
    @endif
@endsection
