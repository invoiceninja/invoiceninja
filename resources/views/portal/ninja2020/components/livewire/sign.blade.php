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

