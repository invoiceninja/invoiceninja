<div x-data="portalSignature" class="bg-white p-4 sm:p-6 rounded-lg shadow-lg">
    <h2 class="text-xl mb-4">{{ ctrans('texts.sign_here') }}</h2>
    <canvas wire:ignore x-ref="canvas" id="signature-pad" class="portal-signature border border-gray-300" aria-label="{{ ctrans('texts.sign_here') }}"></canvas>
    <p class="mt-3 text-sm">{{ ctrans('texts.sign_here_ux_tip') }}</p>
    <div class="flex flex-col sm:flex-row-reverse gap-3 mt-4">
        <button type="button" id="save-button" class="button button-primary bg-primary w-full sm:w-auto min-h-11" :disabled="!signed" wire:loading.attr="disabled" @click="save()">{{ ctrans('texts.next') }}</button>
        <button type="button" id="clear-signature" class="button button-secondary w-full sm:w-auto min-h-11" @click="clear()">{{ ctrans('texts.clear') }}</button>
    </div>
</div>
