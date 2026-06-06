@switch($field['key'])
    @case('custom_value1')
        @switch($field['type'])
            @case('date')
                <input id="custom_value1" type="date" class="input w-full" wire:model="custom_value1" />
                @break
            @case('textarea')
                <textarea id="custom_value1" class="input w-full" rows="4" wire:model="custom_value1"></textarea>
                @break
            @case('dropdown')
                <select id="custom_value1" class="input w-full form-select bg-white" wire:model="custom_value1">
                    <option value=""></option>
                    @foreach($field['options'] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @break
            @case('switch')
                <select id="custom_value1" class="input w-full form-select bg-white" wire:model="custom_value1">
                    <option value=""></option>
                    <option value="yes">{{ ctrans('texts.yes') }}</option>
                    <option value="no">{{ ctrans('texts.no') }}</option>
                </select>
                @break
            @default
                <input id="custom_value1" type="text" class="input w-full" wire:model="custom_value1" />
        @endswitch
        @break

    @case('custom_value2')
        @switch($field['type'])
            @case('date')
                <input id="custom_value2" type="date" class="input w-full" wire:model="custom_value2" />
                @break
            @case('textarea')
                <textarea id="custom_value2" class="input w-full" rows="4" wire:model="custom_value2"></textarea>
                @break
            @case('dropdown')
                <select id="custom_value2" class="input w-full form-select bg-white" wire:model="custom_value2">
                    <option value=""></option>
                    @foreach($field['options'] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @break
            @case('switch')
                <select id="custom_value2" class="input w-full form-select bg-white" wire:model="custom_value2">
                    <option value=""></option>
                    <option value="yes">{{ ctrans('texts.yes') }}</option>
                    <option value="no">{{ ctrans('texts.no') }}</option>
                </select>
                @break
            @default
                <input id="custom_value2" type="text" class="input w-full" wire:model="custom_value2" />
        @endswitch
        @break

    @case('custom_value3')
        @switch($field['type'])
            @case('date')
                <input id="custom_value3" type="date" class="input w-full" wire:model="custom_value3" />
                @break
            @case('textarea')
                <textarea id="custom_value3" class="input w-full" rows="4" wire:model="custom_value3"></textarea>
                @break
            @case('dropdown')
                <select id="custom_value3" class="input w-full form-select bg-white" wire:model="custom_value3">
                    <option value=""></option>
                    @foreach($field['options'] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @break
            @case('switch')
                <select id="custom_value3" class="input w-full form-select bg-white" wire:model="custom_value3">
                    <option value=""></option>
                    <option value="yes">{{ ctrans('texts.yes') }}</option>
                    <option value="no">{{ ctrans('texts.no') }}</option>
                </select>
                @break
            @default
                <input id="custom_value3" type="text" class="input w-full" wire:model="custom_value3" />
        @endswitch
        @break

    @case('custom_value4')
        @switch($field['type'])
            @case('date')
                <input id="custom_value4" type="date" class="input w-full" wire:model="custom_value4" />
                @break
            @case('textarea')
                <textarea id="custom_value4" class="input w-full" rows="4" wire:model="custom_value4"></textarea>
                @break
            @case('dropdown')
                <select id="custom_value4" class="input w-full form-select bg-white" wire:model="custom_value4">
                    <option value=""></option>
                    @foreach($field['options'] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @break
            @case('switch')
                <select id="custom_value4" class="input w-full form-select bg-white" wire:model="custom_value4">
                    <option value=""></option>
                    <option value="yes">{{ ctrans('texts.yes') }}</option>
                    <option value="no">{{ ctrans('texts.no') }}</option>
                </select>
                @break
            @default
                <input id="custom_value4" type="text" class="input w-full" wire:model="custom_value4" />
        @endswitch
        @break
@endswitch
