<div class="grid grid-cols-1 md:grid-cols-2">
    <div class="p-2">
        @livewire('flow2.invoice-summary')
    </div>

    <div class="p-2">
        @livewire($this->component, [], key($this->componentUniqueId()))
    </div>
</div>
