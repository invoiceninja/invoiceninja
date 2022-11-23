<style type="text/css">
    
</style>

<div class="grid grid-cols-12">

    <div class="col-span-12 xl:col-span-8 bg-gray-50 flex flex-col max-h-100px">
        <div class="w-full p-8 md:max-w-3xl">

            <img class="object-scale-down" style="max-height: 100px;"src="{{ $subscription->company->present()->logo }}" alt="{{ $subscription->company->present()->name }}">

            <h1 id="billing-page-company-logo" class="text-3xl font-bold tracking-wide mt-6">
            {{ $subscription->name }}
            </h1>

        </div>
    </div>
    <div class="col-span-12 xl:col-span-4 bg-blue-500 flex flex-col item-center">
        <div class="w-full p-4 md:max-w-3xl">


        </div>
    </div>

</div>

<div class="grid grid-cols-12 border-4 border-gray-600">

    <div class="col-span-12 xl:col-span-8 bg-gray-50 flex flex-col">
        <div class="w-full p-8 md:max-w-3xl">

product block

        </div>
    </div>
    <div class="col-span-12 xl:col-span-4 bg-blue-500 flex flex-col item-center">
        <div class="w-full p-4 md:max-w-3xl">

customer form block

        </div>
    </div>
</div>
