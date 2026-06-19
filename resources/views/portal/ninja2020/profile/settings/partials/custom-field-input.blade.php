@switch($field['type'])
    @case('date')
        <input id="{{ $field['key'] }}" type="date" class="input w-full" wire:model="{{ $field['key'] }}" />
        @break

    @case('textarea')
        <textarea id="{{ $field['key'] }}" class="input w-full" rows="4" wire:model="{{ $field['key'] }}"></textarea>
        @break

    @case('dropdown')
        <select id="{{ $field['key'] }}" class="input w-full form-select bg-white" wire:model="{{ $field['key'] }}">
            <option value=""></option>
            @foreach($field['options'] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
        @break

    @case('switch')
        <select id="{{ $field['key'] }}" class="input w-full form-select bg-white" wire:model="{{ $field['key'] }}">
            <option value=""></option>
            <option value="yes">{{ ctrans('texts.yes') }}</option>
            <option value="no">{{ ctrans('texts.no') }}</option>
        </select>
        @break

    @default
        <input id="{{ $field['key'] }}" type="text" class="input w-full" wire:model="{{ $field['key'] }}" />
@endswitch
