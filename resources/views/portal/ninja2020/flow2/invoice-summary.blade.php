<div class="w-full">
   <div class="rounded-lg border bg-card bg-white text-card-foreground shadow-sm overflow-hidden" x-chunk="An order details card with order details, shipping information, customer information and payment information.">
      <div class="space-y-1.5 p-6 flex flex-row items-start bg-muted/50">
         <div class="grid gap-0.5">
            <h3 class="font-semibold tracking-tight group flex items-center gap-2 text-lg">
               {{ ctrans('texts.invoice') }} {{ $invoice->number }}
               <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-copy h-3 w-3">
                     <rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
                     <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
                  </svg>
                  <span class="sr-only">{{ ctrans('texts.copy') }}</span>
               </button>
            </h3>
            <p class="text-sm text-muted-foreground">{{ ctrans('texts.date') }}:  {{ $invoice->translateDate($invoice->date, $invoice->client->date_format(), $invoice->client->locale()) }}</p>
         </div>

      </div>
      <div class="p-6 text-sm">
         <div class="grid gap-3">
            <div class="font-semibold">{{ ctrans('texts.invoice_details') }} </div>
            <ul class="grid gap-3">
                @foreach($invoice->line_items as $item)
               <li class="flex items-center justify-between"><span class="text-muted-foreground">{{ $item->notes }}<span></span></span><span> {{ App\Utils\Number::formatMoney($item->line_total, $invoice->client) }}</span></li>
                @endforeach
            </ul>
            <div data-orientation="horizontal" role="none" class="shrink-0 bg-border h-[1px] w-full my-2"></div>
            <ul class="grid gap-3">
                @if($invoice->total_taxes > 0)
                <li class="flex items-center justify-between"><span class="text-muted-foreground">{{ ctrans('texts.tax')}}</span><span>{{App\Utils\Number::formatMoney($invoice->total_taxes, $invoice->client) }} </span></li>
                @endif
                <li class="flex items-center justify-between font-semibold"><span class="text-muted-foreground">{{ ctrans('texts.total') }}</span><span>{{ App\Utils\Number::formatMoney($invoice->amount, $invoice->client) }}</span></li>
            </ul>
         </div>
         <div data-orientation="horizontal" role="none" class="shrink-0 bg-border h-[1px] w-full my-4"></div>
         <div class="grid grid-cols-2 gap-4">
            <div class="grid auto-rows-max gap-3">
               <div class="font-semibold"></div>
            </div>
         </div>
         <div data-orientation="horizontal" role="none" class="shrink-0 bg-border h-[1px] w-full my-4"></div>
         <div class="grid gap-3">
            <div class="font-semibold">{{ ctrans('texts.client_information') }}</div>
            <dl class="grid gap-3">
               <div class="flex items-center justify-between">
                  <dt class="text-muted-foreground">{{ ctrans('texts.client') }}</dt>
                  <dd>{{ $invoice->client->present()->name() }}</dd>
               </div>
               <div class="flex items-center justify-between">
                  <dt class="text-muted-foreground">{{ ctrans('texts.email') }}</dt>
                  <dd><a href="mailto:">{{ $invoice->client->present()->email() }}</a></dd>
               </div>
               <div class="flex items-center justify-between">
                  <dt class="text-muted-foreground">{{ ctrans('texts.phone') }}</dt>
                  <dd><a href="tel:">{{ $invoice->client->present()->phone() }} </a></dd>
               </div>
            </dl>
         </div>
         <div data-orientation="horizontal" role="none" class="shrink-0 bg-border h-[1px] w-full my-4"></div>
         <div class="grid gap-3">
            <div class="font-semibold"></div>
            <dl class="grid gap-3">
            </dl>
         </div>
      </div>
   </div>
</div>
