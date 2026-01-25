{{-- Rejection Confirmation Modal (Modal de confirmación de rechazo) --}}
<div style="display: none;" id="displayRejectModal" class="fixed bottom-0 inset-x-0 px-4 pb-4 sm:inset-0 sm:flex sm:items-center sm:justify-center z-50">
    <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity">
        <div class="absolute inset-0 bg-gray-500 opacity-75" id="reject-modal-backdrop"></div>
    </div>

    <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full sm:p-6">
        <div class="sm:flex sm:items-start">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-xl leading-6 font-medium text-gray-900">
                    {{ ctrans('texts.reject_quote') }}
                </h3>
                <p class="mt-2 text-sm text-gray-500">
                    {{ ctrans('texts.reject_quote_confirmation') }}
                </p>

                <div class="mt-4">
                    <label for="reject_reason" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ ctrans('texts.reason') }} ({{ ctrans('texts.optional') }})
                    </label>
                    <textarea 
                        name="reject_reason" 
                        id="reject_reason" 
                        rows="3"
                        class="block w-full rounded-md border-gray-300 bg-gray-100 focus:border-primary-300 focus:bg-white focus:ring focus:ring-primary-200 focus:ring-opacity-50" 
                        placeholder="{{ ctrans('texts.enter_reason') }}"></textarea>
                </div>
            </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
            <div class="flex w-full rounded-md shadow-sm sm:ml-3 sm:w-auto">
                <button type="button" id="reject-confirm-button" class="button button-danger bg-red-500 hover:bg-red-600 text-white w-full sm:w-auto">
                    {{ ctrans('texts.reject') }}
                </button>
            </div>
            <div class="mt-3 flex w-full rounded-md shadow-sm sm:mt-0 sm:w-auto">
                <button type="button" class="button button-secondary w-full sm:w-auto" id="reject-close-button">
                    {{ ctrans('texts.cancel') }}
                </button>
            </div>
        </div>
    </div>
</div>

