@if($this->component === 'App\Livewire\Flow2\DocuNinjaLoader' || $this->component === 'App\Livewire\Flow2\DocuNinja')
    {{-- Full width for docuninja component --}}
    <div class="w-full">
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

        @livewire($this->component, ['invitation_id' => $this->invitation_id, '_key' => $_key], key($this->componentUniqueId()))
    </div>
@else
    {{-- Two column layout for other components --}}
    <div class="grid grid-cols-1 md:grid-cols-2">
        <div class="p-2">
            @livewire('flow2.invoice-summary', ['_key' => $_key])
        </div>

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

            @livewire($this->component, ['invitation_id' => $this->invitation_id, '_key' => $_key], key($this->componentUniqueId()))
        </div>
    </div>
@endif
