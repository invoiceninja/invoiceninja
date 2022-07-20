<div style="display: none;" id="displaySignatureModal" class="fixed bottom-0 inset-x-0 px-4 pb-4 sm:inset-0 sm:flex sm:items-center sm:justify-center">
    <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity">
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
    </div>

    <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full sm:p-6">
        <div class="sm:flex sm:items-start">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-red-600" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    {{ ctrans('texts.sign_here') }}
                </h3>
                <div class="mt-2">
                    <p class="text-sm leading-5 text-gray-500">
                        <canvas id="signature-pad" class="signature-pad border rounded" width=400 height=200></canvas>
                    </p>
                </div>
            </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
            <div class="flex w-full rounded-md shadow-sm sm:ml-3 sm:w-auto">
                <button type="button" id="signature-next-step" class="button button-primary bg-primary" @click="document.getElementById('displaySignatureModal').style.display = 'none';">
                    {{ ctrans('texts.next_step') }}
                </button>
            </div>
            <div class="mt-3 flex w-full rounded-md shadow-sm sm:mt-0 sm:w-auto">
                <button id="close_button" onclick="document.getElementById('displaySignatureModal').style.display = 'none'; return true;" type="button" class="button button-secondary">
                    {{ ctrans('texts.close') }}
                </button>
            </div>
        </div>
    </div>
</div>
