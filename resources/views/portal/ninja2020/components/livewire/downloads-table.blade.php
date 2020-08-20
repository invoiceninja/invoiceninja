<div>
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <span class="mr-2 text-sm hidden md:block">{{ ctrans('texts.per_page') }}</span>
            <select wire:model="per_page" class="form-select py-1 text-sm">
                <option>5</option>
                <option selected>10</option>
                <option>15</option>
                <option>20</option>
            </select>
            <button x-on:click="document.getElementById('multiple-downloads').submit()" class="button button-primary py-2 ml-2">
                <span class="hidden md:block">
                    {{ ctrans('texts.download_selected') }}
                </span>
                <svg class="md:hidden" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 17 12 21 16 17"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path></svg>
            </button>
        </div>
        <div class="flex items-center">
            <div class="mr-3">
                <input wire:click="statusChange('resources')" type="checkbox" class="form-checkbox cursor-pointer" id="resources-checkbox" checked>
                <label for="resources-checkbox" class="text-sm cursor-pointer">{{ ctrans('texts.resources') }}</label>
            </div>
            <div class="mr-3">
                <input wire:click="statusChange('client')" type="checkbox" class="form-checkbox cursor-pointer" id="client-checkbox">
                <label for="client-checkbox" class="text-sm cursor-pointer">{{ ctrans('texts.client') }}</label>
            </div>
        </div>
    </div>
    <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="align-middle inline-block min-w-full overflow-hidden rounded">
            <table class="min-w-full shadow rounded border border-gray-200 mt-4 credits-table">
                <thead>
                    <tr>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider" />
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('name')" class="cursor-pointer">
                                {{ ctrans('texts.name') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('documentable_type')" class="cursor-pointer">
                                {{ ctrans('texts.resource') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('type')" class="cursor-pointer">
                                {{ ctrans('texts.type') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('size')" class="cursor-pointer">
                                {{ ctrans('texts.size') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.download') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider" />
                    </tr>
                </thead>
                <tbody>
                    @forelse($downloads as $download)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                <input type="checkbox" class="form-checkbox cursor-pointer" onchange="appendToElement('multiple-downloads', '{{ $download->hashed_id }}')" />
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ Illuminate\Support\Str::limit($download->name, 20) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ ((new \ReflectionClass($download->documentable))->getShortName()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ App\Models\Document::$types[$download->type]['mime'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $download->size / 1000 }} kB
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                <a href="{{ route('client.downloads.download', $download->hashed_id) }}" class="text-black hover:text-blue-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download-cloud"><polyline points="8 17 12 21 16 17"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path></svg>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                <a href="{{ route('client.downloads.show', $download->hashed_id) }}" class="button-link">
                                    {{ ctrans('texts.view') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500" colspan="100%">
                                {{ ctrans('texts.no_results') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="flex justify-center md:justify-between mt-6 mb-6">
        @if($downloads->total() > 0)
            <span class="text-gray-700 text-sm hidden md:block">
                {{ ctrans('texts.showing_x_of', ['first' => $downloads->firstItem(), 'last' => $downloads->lastItem(), 'total' => $downloads->total()]) }}
            </span>
        @endif
        {{ $downloads->links() }}
    </div>
</div>