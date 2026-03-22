@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.dashboard'))

@section('header')
    @if(!empty($client->getSetting('custom_message_dashboard')))
        @component('portal.ninja2020.components.message')
            {!! CustomMessage::client($client)
                ->company($client->company)
                ->message($client->getSetting('custom_message_dashboard')) !!}
        @endcomponent
    @endif
@endsection

@section('body')
    <div>
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            {{ ctrans('texts.hello') }}, {{ $contact->first_name }}
        </h3>

        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="bg-white overflow-hidden shadow rounded">
                <div class="px-4 py-5 sm:p-6">
                    <dl>
                        <dt class="text-sm leading-5 font-medium text-gray-500 truncate">
                            {{ ctrans('texts.paid_to_date') }}
                        </dt>
                        <dd class="mt-1 text-3xl leading-9 font-semibold text-gray-900">
                            {{ App\Utils\Number::formatMoney($client->paid_to_date, $client) }}
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded">
                <div class="px-4 py-5 sm:p-6">
                    <dl>
                        <dt class="text-sm leading-5 font-medium text-gray-500 truncate">
                            {{ ctrans('texts.open_balance') }}
                        </dt>
                        <dd class="mt-1 text-3xl leading-9 font-semibold text-gray-900">
                            {{ App\Utils\Number::formatMoney($client->balance, $client) }}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-12 gap-4 mt-6">
        <div class="col-span-6">
            <div class="bg-white rounded shadow px-4 py-5 border-b border-gray-200 sm:px-6">
                <div class="-ml-4 -mt-4 flex justify-between items-center flex-wrap sm:flex-nowrap">
                    <div class="ml-4 mt-4 w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 capitalize">
                            {{ ctrans('texts.group_documents') }}
                        </h3>

                        <div class="flex flex-col h-auto overflow-y-auto">
                            @if($client->group_settings)
                                @forelse($client->group_settings->documents as $document)
                                    <a href="{{ route('client.documents.show', $document->hashed_id) }}" target="_blank"
                                       class="block inline-flex items-center text-sm button-link text-primary">
                                        <span>{{ Illuminate\Support\Str::limit($document->name, 40) }}</span>

                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                             stroke-linejoin="round" class="ml-2 text-primary h-6 w-4">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                            <polyline points="15 3 21 3 21 9"></polyline>
                                            <line x1="10" y1="14" x2="21" y2="3"></line>
                                        </svg>
                                    </a>
                                @empty
                                    <p class="text-sm">{{ ctrans('texts.no_records_found') }}.</p>
                                @endforelse
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-6">
            <div class="bg-white rounded shadow px-4 py-5 border-b border-gray-200 sm:px-6">
                <div class="-ml-4 -mt-4 flex justify-between items-center flex-wrap sm:flex-nowrap">
                    <div class="ml-4 mt-4 w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 capitalize">
                            {{ ctrans('texts.default_documents') }}
                        </h3>

                        <div class="flex flex-col h-auto overflow-y-auto">
                            @forelse($client->company->documents as $document)
                                <a href="{{ route('client.documents.show', $document->hashed_id) }}" target="_blank"
                                   class="block inline-flex items-center text-sm button-link text-primary">
                                    <span>{{ Illuminate\Support\Str::limit($document->name, 40) }}</span>

                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round" class="ml-2 text-primary h-6 w-4">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                        <polyline points="15 3 21 3 21 9"></polyline>
                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                    </svg>
                                </a>
                            @empty
                                <p class="text-sm">{{ ctrans('texts.no_records_found') }}.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
