<div class="grid grid-cols-12 bg-gray-50">
    
    <div
    
    class="col-span-12 xl:col-span-6 bg-white flex flex-col items-center lg:h-screen"
    >
        <div class="w-full p-10 lg:mt-24 md:max-w-xl">
            <img
                class="h-8"
                src="{{ $subscription->company->present()->logo }}"
                alt="{{ $subscription->company->present()->name }}"
            />


            <div class="my-10" id="container">
                @livewire($this->component, ['context' => $context, 'subscription' => $this->subscription], key($id))
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-6">
        <div class="sticky top-0">
            <div class="w-full p-10 lg:mt-24 md:max-w-xl">
                <div class="my-6 space-y-10 xl:ml-5">
                    @livewire('billing-portal.summary', ['subscription' => $subscription, 'context' => $context])
                </div>
            </div>
        </div>
    </div>
</div>
