
<form action="{{ route('client.quotes.bulk') }}" method="post" id="approve-form">
@csrf
<input type="hidden" name="action" value="approve" id="quote-action">
<input type="hidden" name="process" value="true">
<input type="hidden" name="quotes[]" value="{{ $quote->hashed_id }}">
<input type="hidden" name="signature">
<input type="hidden" name="user_input" value="">
</form>

<form action="{{ route('client.quotes.bulk') }}" method="post" id="reject-form">
@csrf
<input type="hidden" name="action" value="reject">
<input type="hidden" name="process" value="true">
<input type="hidden" name="quotes[]" value="{{ $quote->hashed_id }}">
<input type="hidden" name="user_input" value="">
</form>

<div class="bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <div class="sm:flex sm:items-start sm:justify-between">
            <div>
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    {{ ctrans('texts.approve') }} / {{ ctrans('texts.reject') }}
                </h3>
            </div>

            <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                @yield('quote-not-approved-right-side')
                <div class="inline-flex rounded-md shadow-sm mr-2">
                    <button type="button"
                        class="button button-primary bg-primary"
                        id="approve-button">{{ ctrans('texts.approve') }}</button>
                </div>

                <div class="inline-flex rounded-md shadow-sm">
                    <button type="button"
                        class="button button-secondary bg-red-500 text-white hover:bg-red-600"
                        id="reject-button">{{ ctrans('texts.reject') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
