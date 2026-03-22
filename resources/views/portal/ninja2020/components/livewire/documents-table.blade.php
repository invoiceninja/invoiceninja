<div>
    <div class="space-x-2 flex flex-row -mt-6 overflow-x-auto inline-block pb-4">
        <button 
            class="button border border-transparent hover:border-gray-600 {{ $tab === 'documents' ? 'border-gray-600' : '' }}"
            wire:click="updateResources('documents')" />
                {{ ctrans('texts.my_documents') }}
        </button>

        <button 
            class="button border border-transparent hover:border-gray-600 {{ $tab === 'credits' ? 'border-gray-600' : '' }}"ž
            wire:click="updateResources('credits')" />
                {{ ctrans('texts.credits') }}
        </button>

        <button 
            class="button border border-transparent hover:border-gray-600 {{ $tab === 'invoices' ? 'border-gray-600' : '' }}"ž
            wire:click="updateResources('invoices')" />
                {{ ctrans('texts.invoices') }}
        </button>

        <button 
            class="button border border-transparent hover:border-gray-600 {{ $tab === 'payments' ? 'border-gray-600' : '' }}"ž
            wire:click="updateResources('payments')" />
                {{ ctrans('texts.payments') }}
        </button>

        <button 
            class="button border border-transparent hover:border-gray-600 {{ $tab === 'projects' ? 'border-gray-600' : '' }}"ž
            wire:click="updateResources('projects')" />
                {{ ctrans('texts.projects') }}
        </button>

        <button 
            class="button border border-transparent hover:border-gray-600 {{ $tab === 'quotes' ? 'border-gray-600' : '' }}"ž
            wire:click="updateResources('quotes')" />
                {{ ctrans('texts.quotes') }}
        </button>   
        
        <button 
            class="button border border-transparent hover:border-gray-600 {{ $tab === 'recurringInvoices' ? 'border-gray-600' : '' }}"ž
            wire:click="updateResources('recurringInvoices')" />
                {{ ctrans('texts.recurring_invoices') }}
        </button>   

        <button 
            class="button border border-transparent hover:border-gray-600 {{ $tab === 'tasks' ? 'border-gray-600' : '' }}"ž
            wire:click="updateResources('tasks')" />
                {{ ctrans('texts.tasks') }}
        </button>   
    </div>

    <div class="flex items-center justify-between mt-6">
        <div class="flex items-center">
            <span class="mr-2 text-sm hidden md:block">{{ ctrans('texts.per_page') }}</span>
            <select wire:model.live="per_page" class="form-select py-1 text-sm">
                <option>5</option>
                <option selected>10</option>
                <option>15</option>
                <option>20</option>
            </select>
            <button onclick="document.getElementById('multiple-downloads').submit(); setTimeout(() => this.disabled = true, 0); setTimeout(() => this.disabled = false, 5000);" class="button button-primary bg-primary py-2 ml-2">
                <span class="hidden md:block">
                    {{ ctrans('texts.download_selected') }}
                </span>
                <svg class="md:hidden" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 17 12 21 16 17"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path></svg>
            </button>
        </div>
    </div>
    <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="align-middle inline-block min-w-full overflow-hidden rounded">
            <table class="min-w-full shadow rounded border border-gray-200 mt-4 credits-table">
                <thead>
                    <tr>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider" />
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('name')" class="cursor-pointer">
                                {{ ctrans('texts.name') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('size')" class="cursor-pointer">
                                {{ ctrans('texts.size') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                            {{ ctrans('texts.download') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                                <input type="checkbox" class="form-checkbox cursor-pointer" onchange="appendToElement('multiple-downloads', '{{ $document->hashed_id }}')" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500 truncate">
                                {{ $document->name }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                                {{ $document->size / 1000 }} kB
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                                <a href="{{ route('client.documents.download', $document->hashed_id) }}" class="text-black hover:text-blue-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download-cloud"><polyline points="8 17 12 21 16 17"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path></svg>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                                <a href="{{ route('client.documents.show', $document->hashed_id) }}" class="button-link text-primary">
                                    {{ ctrans('texts.view') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500" colspan="100%">
                                {{ ctrans('texts.no_results') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="flex justify-center md:justify-between mt-6 mb-6">
        @if($documents->total() > 0)
            <span class="text-gray-700 text-sm hidden md:block">
                {{ ctrans('texts.showing_x_of', ['first' => $documents->firstItem(), 'last' => $documents->lastItem(), 'total' => $documents->total()]) }}
            </span>
        @endif
        {{ $documents->links('portal/ninja2020/vendor/pagination') }}
    </div>
</div>
