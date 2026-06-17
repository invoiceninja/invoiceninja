@switch($field['type'])
    @case('date')
        <input
            id="{{ $field['key'] }}"
            type="date"
            class="input w-full"
            name="{{ $field['key'] }}"
            value="{{ old($field['key']) }}"
        />
        @break

    @case('textarea')
        <textarea
            id="{{ $field['key'] }}"
            class="input w-full"
            rows="4"
            name="{{ $field['key'] }}"
        >{{ old($field['key']) }}</textarea>
        @break

    @case('dropdown')
        <select
            id="{{ $field['key'] }}"
            class="input w-full form-select bg-white"
            name="{{ $field['key'] }}"
        >
            <option value=""></option>
            @foreach($field['options'] as $option)
                <option value="{{ $option }}" {{ old($field['key']) === $option ? 'selected' : '' }}>
                    {{ $option }}
                </option>
            @endforeach
        </select>
        @break

    @case('switch')
        <select
            id="{{ $field['key'] }}"
            class="input w-full form-select bg-white"
            name="{{ $field['key'] }}"
        >
            <option value=""></option>
            <option value="yes" {{ old($field['key']) === 'yes' ? 'selected' : '' }}>{{ ctrans('texts.yes') }}</option>
            <option value="no" {{ old($field['key']) === 'no' ? 'selected' : '' }}>{{ ctrans('texts.no') }}</option>
        </select>
        @break

    @default
        <input
            id="{{ $field['key'] }}"
            type="text"
            class="input w-full"
            name="{{ $field['key'] }}"
            value="{{ old($field['key']) }}"
        />
@endswitch
