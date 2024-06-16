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
        @livewire('required-client-info', ['db' => $company->db, 'fields' => method_exists($gateway, 'getClientRequiredFields') ? $gateway->getClientRequiredFields() : [], 'contact_id' => auth()->guard('contact')->user()->id, 'countries' => $countries, 'company_id' => $company->id, 'company_gateway_id' => $gateway->company_gateway ? $gateway->company_gateway->id : $gateway->id, 'form_only' => true, 'is_subscription' => true])
    </div>
</div>
