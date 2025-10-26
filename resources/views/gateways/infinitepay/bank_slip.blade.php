@extends('layouts.client')

@section('content')
<div class="container mx-auto p-6">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-2xl font-bold mb-6">Boleto Bancário</h2>
        
        <div class="mb-6">
            <p class="text-gray-600 mb-4">Seu boleto foi gerado com sucesso!</p>
            
            @if(isset($charge['bank_slip']['barcode']))
            <div class="bg-gray-100 p-4 rounded mb-4">
                <code class="text-sm">{{ $charge['bank_slip']['barcode'] }}</code>
            </div>
            @endif
            
            @if(isset($charge['bank_slip']['pdf_url']))
            <a href="{{ $charge['bank_slip']['pdf_url'] }}" target="_blank" 
               class="inline-block bg-blue-500 text-white px-6 py-3 rounded hover:bg-blue-600">
                Baixar Boleto (PDF)
            </a>
            @endif
        </div>
        
        <div class="mt-6 border-t pt-4">
            <p class="text-sm text-gray-500">
                <strong>Vencimento:</strong> {{ isset($charge['due_date']) ? date('d/m/Y', strtotime($charge['due_date'])) : '' }}
            </p>
            <p class="text-sm text-gray-500 mt-2">
                O pagamento será confirmado automaticamente após compensação bancária
            </p>
        </div>
    </div>
</div>
@endsection