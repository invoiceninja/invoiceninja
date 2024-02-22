<div>
    @if($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        @livewire('required-client-info', [
            'fields' => method_exists($gateway, 'getClientRequiredFields') ? $gateway->getClientRequiredFields() : [], 
            'contact' => auth()->guard('contact')->user(), 
            'countries' => $countries, 
            'company' => $company, 
            'company_gateway_id' => $gateway->company_gateway ? $gateway->company_gateway->id : $gateway->id,
            'form_only' => true
        ])
    </div>
</div>
