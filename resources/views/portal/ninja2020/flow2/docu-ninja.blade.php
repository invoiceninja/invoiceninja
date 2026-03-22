<link rel="stylesheet" href="{{ asset('build/assets/builder2.0.standalone.css') }}">

<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden px-4 py-5 bg-white sm:gap-4 sm:px-6">
    <div class="p-2">
        @if($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @php
            session()->forget('errors');
        @endphp

    </div>
        
    <div id="sign"></div>
</div>
    
@assets
<script src="{{ asset('build/assets/builder.iife.js') }}"></script>
@endassets

@script
<script>
    const doc = '{{ $document }}';
    const invitation = '{{ $invitation }}';
    const sig = '{{ $sig }}';
    const company = '{{ $company_key }}';

    const mount = document.getElementById("sign");

    new DocuNinjaSign({ document: doc, invitation, sig, endpoint: '{{ config('ninja.docuninja_api_url') }}', company }).mount(mount);

    window.addEventListener('builder:sign.submit.success', function () {
        Livewire.dispatch('docuninja-signature-captured');
    });
</script>
@endscript
