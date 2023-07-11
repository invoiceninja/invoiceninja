
<style>


table, th, td {
  border: 1px solid black;
  border-collapse: collapse;
}

td
{
 max-width: 1px;
 overflow: hidden;
 text-overflow: ellipsis;
 white-space: nowrap;
}

span {
    display: block;
    padding: 3px;
    margin-right:10px;
}

.icon {
  display: inline-block;
  vertical-align: middle;
}

</style>
<div class="w-full">
    <div class="flex flex-row content-center border-fuchsia-600 border-b-2 pb-3">
        <div id="company-details" class="mx-auto">
            {!! $company_details !!}
        </div>

        <div id="company-address"  class="mx-auto">
            {!! $company_address !!}
        </div>
    </div>

    <div class="mt-3"> {!! $entity_details !!}</div>

    <div id="user-details" class="mt-3">
        {!! $user_details !!}
    </div>

    @if($products->count() > 0)
    <div id="product-details"  class="py-6">
        <table width="100%">
            <thead>
                <tr>
                    <th style="text-align:left; width:70%; padding-left:2px;">Item</th>
                    <th style="text-align:right; width:30%; padding-right:2px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr style="display: table-row;">
                    <td>
                        <div class="product-information">
                            <div class="item-details">
                                <p class="px-2 mt-2">{{ $product['quantity'] }} × {{ $product['cost'] }}</p> 
                                <p class="overflow-ellipsis overflow-hidden px-2 mb-2">{{ $product['notes'] }}</p>
                            </div>
                        </div>
                    </td>
                    <td style="text-align:right; padding-right:2px;">{{ $product['line_total'] }}</td>
                </tr>
                @endforeach                   
            </tbody>
        </table>
    </div>
    @endif 
    @if($services->count() > 0)
    <div id="task-details" class="py-6">
        <table width="100%">
            <thead>
                <tr class="border-bottom>
                    <th style="text-align:left; width:70%; padding-left:2px;">Service</th>
                    <th style="text-align:right; width:30%; padding-right:2px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($services as $service)
                <tr style="display: table-row;">
                    <td>
                        <div class="">
                            <div class="">
                                <p class="px-2 mt-2">{{ $service['quantity'] }} × {{ $service['cost'] }}</p> 
                                <p class="overflow-ellipsis overflow-hidden px-2 mb-2">{{ $service['notes'] }}</p>
                            </div>
                        </div>
                    </td>
                    <td style="text-align:right; padding-right:2px;">{{ $service['line_total'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    <div id="totals" class="mb-20">
        <table width="100%">
            <thead>
            </thead>
            <tbody>
                <tr style="display: table-row;">
                    <td>
                        <div class="">
                            <div class="">
                                <p class="px-2">{{ ctrans('texts.total') }}</p> 
                            </div>
                        </div>
                    </td>
                    <td style="text-align:right; padding-right:2px;">{{ $amount }}</td>
                </tr>
                 <tr style="display: table-row;">
                    <td>
                        <div class="">
                            <div class="">
                                <p class="px-2">{{ ctrans('texts.balance') }}</p> 
                            </div>
                        </div>
                    </td>
                    <td style="text-align:right; padding-right:2px;">{{ $balance }}</td>
                </tr>
            </tbody>
        </table>

    </div>

    @if(strlen($entity->public_notes) > 3)
    <div x-data="{ show_notes: false }" class="mb-10">
        
        <button @click="show_notes = !show_notes" :aria-expanded="show_notes ? 'true' : 'false'" :class="{ 'active': show_notes }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
           <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><!--! Font Awesome Free 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
          <span>{{ ctrans('texts.notes') }}</span>
        </button>
        
        <div id="notes" class="py-10 border-b-2 border-fuschia-600"  x-show="show_notes">     
            {{ $entity->public_notes }}
        </div>

    </div>
    @endif

    @if(strlen($entity->terms) > 3)
    <div x-data="{ show_terms: false }" class="mb-10">

        <button @click="show_terms = !show_terms" :aria-expanded="show_terms ? 'true' : 'false'" :class="{ 'active': show_terms }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><!--! Font Awesome Free 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
        <span>{{ ctrans('texts.terms') }}</span>
        </button>

        <div id="terms" class="py-10 border-b-2 border-fuschia-600"  x-show="show_terms">
            {{ $entity->terms }}
        </div>

    </div>
    @endif

    @if(strlen($entity->footer) > 3)
    <div x-data="{ show_footer: false }" class="mb-10">

        <button @click="show_footer = !show_footer" :aria-expanded="show_footer ? 'true' : 'false'" :class="{ 'active': show_footer }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><!--! Font Awesome Free 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
            <span>{{ ctrans('texts.footer') }}</span>
        </button>

        <div id="terms" class="py-10 border-b-2 border-fuschia-600"  x-show="show_footer">
            {{ $entity->footer }}
        </div>


    </div>
    @endif

