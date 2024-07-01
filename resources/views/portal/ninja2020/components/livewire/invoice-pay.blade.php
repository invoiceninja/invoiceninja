<div class="grid grid-cols-1 md:grid-cols-2">
    <div class="p-2">
        @livewire('invoice-summary',['context' => $context])
    </div>

    <div class="p-2">
        @livewire($this->component,['context' => $context], key($this->componentUniqueId()))
    </div>
    
</div>
