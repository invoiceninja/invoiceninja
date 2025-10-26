@extends('layouts.client')

@section('content')
<div class="container mx-auto p-6">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-2xl font-bold mb-6">Pagamento via PIX</h2>
        
        <div class="text-center mb-6">
            <p class="text-gray-600 mb-4">Escaneie o QR Code ou copie o código abaixo:</p>
            
            @if(isset($charge['pix']['qr_code']))
            <img src="{{ $charge['pix']['qr_code'] }}" alt="QR Code PIX" class="mx-auto mb-4" style="max-width: 300px;">
            @endif
            
            @if(isset($charge['pix']['code']))
            <div class="bg-gray-100 p-4 rounded mb-4">
                <code class="text-sm break-all">{{ $charge['pix']['code'] }}</code>
            </div>
            <button onclick="copyPixCode()" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                Copiar Código PIX
            </button>
            @endif
        </div>
        
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-500">O pagamento será confirmado automaticamente</p>
        </div>
    </div>
</div>

<script>
function copyPixCode() {
    const code = '{{ $charge['pix']['code'] ?? '' }}';
    navigator.clipboard.writeText(code);
    alert('Código PIX copiado!');
}
</script>
@endsection