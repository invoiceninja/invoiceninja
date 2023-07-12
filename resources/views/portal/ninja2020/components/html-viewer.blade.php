
<style>

table, th, td {
  /* border: 1px solid black; */
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
<div class="w-full bg-white py-3 border-2 shadow sm:rounded-lg">

    <div class="px-3 border-fuchsia-600 border-b-2 pb-3">
    
        <div id="company-details" class="mx-auto">
            {!! $company_details !!}
        </div>

    </div>

    <div class="border-fuchsia-600 border-b-2 pb-3 mt-3">

        <div class=""> {!! $entity_details !!} </div>

    </div>


    <div id="user-details" class="mt-3 px-3 border-b-2 border-fuschia-600 flex flex-col items-end"> 

        <div x-data="{ show_user: false }" class="mb-3">

            <button @click="show_user = !show_user" :aria-expanded="show_user ? 'true' : 'false'" :class="{ 'active': show_user }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
                <span class="overflow-ellipsis  overflow-hidden">{{ $user_name }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
            </button>

            <div id="terms" class="py-3"  x-show="show_user">
                {!! $user_details !!}
            </div>


        </div>
         
    </div>

    @if($products->count() > 0)
    <div id="product-details" class="py-6 mr-5 ml-5">
        <table width="100%">
            <thead>
                <tr class="border-b-2">
                    <th style="text-align:left; width:70%; padding-left:2px;">Item</th>
                    <th style="text-align:right; width:30%; padding-right:2px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr style="display: table-row;" class="border-b-2">
                    <td>
                        <div class="product-information">
                            <div class="item-details">
                                <p class="mt-2">{{ $product['quantity'] }} × {{ $product['cost'] }}</p> 
                                <p class="overflow-ellipsis overflow-hidden px-1 mb-2">{{ $product['notes'] }}</p>
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
    <div id="task-details" class="py-6 mr-3 ml-3">
        <table width="100%">
            <thead>
                <tr class="border-bottom">
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
                                <p class="mt-2">{{ $service['quantity'] }} × {{ $service['cost'] }}</p> 
                                <p class="overflow-ellipsis overflow-hidden px-1 mb-2">{{ $service['notes'] }}</p>
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
    <div id="totals" class="mb-10 mr-3 ml-3">
        <table width="100%">
            <thead>
            </thead>
            <tbody>
                <tr style="display: table-row;">
                    <td>
                        <div class="">
                            <div class="">
                                <p class="px-2 text-lg">{{ ctrans('texts.total') }}</p> 
                            </div>
                        </div>
                    </td>
                    <td style="text-align:right; padding-right:10px;" class="text-lg">{{ $amount }}</td>
                </tr>
                 <tr style="display: table-row;">
                    <td>
                        <div class="">
                            <div class="">
                                <p class="px-2 text-lg">{{ ctrans('texts.balance') }}</p> 
                            </div>
                        </div>
                    </td>
                    <td style="text-align:right; padding-right:10px;" class="text-lg">{{ $balance }}</td>
                </tr>
            </tbody>
        </table>

    </div>

    @if(strlen($entity->public_notes) > 3)
    <div x-data="{ show_notes: false }" class="mb-10 mr-5 ml-5 flex flex-col items-end">
        
        <button @click="show_notes = !show_notes" :aria-expanded="show_notes ? 'true' : 'false'" :class="{ 'active': show_notes }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
            <span>{{ ctrans('texts.notes') }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
        </button>
        
        <div id="notes" class="py-10 border-b-2 border-fuschia-600"  x-show="show_notes">     
            {{ strip_tags($entity->public_notes) }}
        </div>

    </div>
    @endif

    @if(strlen($entity->terms) > 3)
    <div x-data="{ show_terms: false }" class="mb-10 mr-5 ml-5 flex flex-col items-end">

        <button @click="show_terms = !show_terms" :aria-expanded="show_terms ? 'true' : 'false'" :class="{ 'active': show_terms }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
            <span>{{ ctrans('texts.terms') }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
        </button>

        <div id="terms" class="py-10 border-b-2 border-fuschia-600"  x-show="show_terms">
            {{ strip_tags($entity->terms) }}
        </div>

    </div>
    @endif

    @if(strlen($entity->footer) > 3)
    <div x-data="{ show_footer: false }" class="mb-10 mr-5 ml-5 flex flex-col items-end">

        <button @click="show_footer = !show_footer" :aria-expanded="show_footer ? 'true' : 'false'" :class="{ 'active': show_footer }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
            <span>{{ ctrans('texts.footer') }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
        </button>

        <div id="terms" class="py-10 border-b-2 border-fuschia-600"  x-show="show_footer">
            {{ strip_tags($entity->footer) }}
        </div>


    </div>
    @endif