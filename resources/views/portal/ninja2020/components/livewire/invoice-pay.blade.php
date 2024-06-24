<div class="grid grid-cols-1 md:grid-cols-2">
    <div>
        @livewire('invoice-summary',['context' => $context])
    </div>

    <div>
        @livewire($this->component,['context' => $context], key($this->componentUniqueId()))
    </div>
    
</div>
