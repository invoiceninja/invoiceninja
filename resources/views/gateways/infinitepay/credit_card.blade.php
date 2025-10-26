@extends('layouts.client')

@section('content')
<div class="container mx-auto p-6">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-2xl font-bold mb-6">Pagamento com Cartão de Crédito</h2>
        
        <form method="POST" action="{{ route('client.payments.process') }}">
            @csrf
            <input type="hidden" name="gateway_type_id" value="{{ \App\Models\GatewayType::CREDIT_CARD }}">
            <input type="hidden" name="invoice_hashed_id" value="{{ $invoices->first()->hashed_id }}">
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Número do Cartão</label>
                <input type="text" name="card_number" class="w-full border rounded px-4 py-2" 
                       placeholder="1234 5678 9012 3456" maxlength="19" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Nome no Cartão</label>
                <input type="text" name="card_holder" class="w-full border rounded px-4 py-2" required>
            </div>
            
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-gray-700 mb-2">Mês</label>
                    <input type="text" name="expiration_month" class="w-full border rounded px-4 py-2" 
                           placeholder="12" maxlength="2" required>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Ano</label>
                    <input type="text" name="expiration_year" class="w-full border rounded px-4 py-2" 
                           placeholder="2025" maxlength="4" required>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">CVV</label>
                    <input type="text" name="cvv" class="w-full border rounded px-4 py-2" 
                           placeholder="123" maxlength="4" required>
                </div>
            </div>
            
            <button type="submit" class="w-full bg-green-500 text-white py-3 rounded hover:bg-green-600">
                Pagar R$ {{ number_format($invoices->first()->balance, 2, ',', '.') }}
            </button>
        </form>
    </div>
</div>
@endsection