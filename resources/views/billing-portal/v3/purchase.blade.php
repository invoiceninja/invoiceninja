<div class="min-w-full flex h-screen">
    <div class="w-full lg:w-1/2 m-10">
        <h1 class="text-3xl">{{ $subscription->name }}</h1>
        <p>C: {{ $this->component }}</p>
        <p>Quantity: {{ $this->context['quantity'] }}</p>
    </div>

    <div class="w-full lg:w-1/2 m-10">
        @livewire($this->component, ['context' => $context, 'subscription' => $this->subscription], key($this->component))
    </div>
</div>
