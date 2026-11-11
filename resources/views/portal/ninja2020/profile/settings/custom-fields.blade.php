<div>
    @if(count($custom_field_definitions))
        <div class="mt-10 sm:mt-6">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <div class="sm:px-0">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.custom_fields') }}</h3>
                    </div>
                </div>

                <div class="mt-5 md:mt-0 md:col-span-2">
                    <div class="shadow overflow-hidden rounded">
                        <div class="px-4 py-5 bg-white sm:p-6">
                            <div class="grid grid-cols-6 gap-6">
                                @foreach($custom_field_definitions as $field)
                                    <div
                                        wire:key="custom-field-{{ $field['key'] }}"
                                        @class([
                                            'col-span-6 sm:col-span-3' => $field['type'] !== 'textarea',
                                            'col-span-6' => $field['type'] === 'textarea',
                                        ])
                                    >
                                        <section class="flex items-center">
                                            <label for="{{ $field['key'] }}" class="input-label">
                                                {{ $field['label'] }}
                                            </label>

                                            @if($field['required'])
                                                <section class="text-red-400 ml-1 text-sm">*</section>
                                            @endif
                                        </section>

                                        @include('portal.ninja2020.profile.settings.partials.custom-field-input', ['field' => $field])

                                        @error($field['key'])
                                            <div class="validation validation-fail">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <button type="button" wire:click="submit" class="button button-primary bg-primary">{{ $saved }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
