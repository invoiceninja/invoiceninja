@component('portal.ninja2020.components.dialog', ['id' => 'displaySignatureModal', 'title' => ctrans('texts.sign_here')])
    <canvas id="signature-pad" class="portal-signature border rounded" aria-label="{{ ctrans('texts.sign_here') }}"></canvas>
    <p class="mt-3 text-sm">{{ ctrans('texts.sign_here_ux_tip') }}</p>
    @slot('actions')
        <button type="button" id="signature-next-step" class="button button-primary bg-primary" disabled>{{ ctrans('texts.next_step') }}</button>
        <button type="button" id="clear-signature" class="button button-secondary">{{ ctrans('texts.clear') }}</button>
        <button type="button" data-dialog-close id="close-signature-button" class="button button-secondary">{{ ctrans('texts.close') }}</button>
    @endslot
@endcomponent
