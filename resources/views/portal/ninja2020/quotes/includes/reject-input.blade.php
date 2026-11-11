@component('portal.ninja2020.components.dialog', ['id' => 'displayRejectModal', 'title' => ctrans('texts.reject_quote')])
    <p class="text-sm text-gray-500 mb-4">{{ ctrans('texts.reject_quote_confirmation') }}</p>
    <label for="reject_reason" class="input-label">{{ ctrans('texts.reason') }} ({{ ctrans('texts.optional') }})</label>
    <textarea name="reject_reason" id="reject_reason" rows="3" class="input w-full" placeholder="{{ ctrans('texts.enter_reason') }}"></textarea>
    @slot('actions')
        <button type="button" id="reject-confirm-button" class="button button-danger">{{ ctrans('texts.reject') }}</button>
        <button type="button" data-dialog-close id="reject-close-button" class="button button-secondary">{{ ctrans('texts.cancel') }}</button>
    @endslot
@endcomponent
