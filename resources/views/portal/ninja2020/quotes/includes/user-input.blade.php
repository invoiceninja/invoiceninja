@component('portal.ninja2020.components.dialog', ['id' => 'displayInputModal', 'title' => ctrans('texts.po_number')])
    <label for="user_input" class="input-label">{{ ctrans('texts.po_number') }}</label>
    <input name="user_input" id="user_input" class="input w-full" type="text">
    @slot('actions')
        <button type="button" id="input-next-step" class="button button-primary bg-primary">{{ ctrans('texts.next_step') }}</button>
        <button type="button" data-dialog-close id="close-input-button" class="button button-secondary">{{ ctrans('texts.close') }}</button>
    @endslot
@endcomponent
