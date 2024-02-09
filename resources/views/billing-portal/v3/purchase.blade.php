<div class="grid grid-cols-12">
    <div class="col-span-12 xl:col-span-6 bg-white flex flex-col items-center lg:h-screen">
        <div class="w-full p-10 lg:mt-24 md:max-w-md">
            <img 
                class="h-8" 
                src="{{ $subscription->company->present()->logo }}"
                alt="{{ $subscription->company->present()->name }}" />

            <div class="my-10" id="container">
                @livewire($this->component, ['context' => $context, 'subscription' => $this->subscription], key($this->component))
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-6 bg-gray-50 flex flex-col items-center">
        <div class="w-full p-10 lg:mt-24 md:max-w-3xl">
            <h1 class="text-3xl">{{ $subscription->name }}</h1>
        </div>
    </div>
</div>
