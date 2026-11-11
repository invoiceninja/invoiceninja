@php
    $analyticsUser = auth()->guard($analyticsGuard ?? 'contact')->user();
    $analytics = [
        'matomo' => isset($company) && $company->matomo_url && $company->matomo_id
            ? ['url' => $company->matomo_url, 'id' => $company->matomo_id, 'userId' => $analyticsUser?->present()->name()] : null,
        'trackingId' => config('services.analytics.tracking_id'),
        'googleAnalyticsKey' => $company->google_analytics_key ?? null,
        'tagManager' => ($cleanLayout ?? false) && \App\Utils\Ninja::isHosted() ? 'GTM-WMJ5W23' : null,
    ];
    $analyticsConfigured = $analytics['matomo'] || filled($analytics['trackingId']) || filled($analytics['googleAnalyticsKey']);
    $analytics['scope'] = hash('sha256', $company->company_key ?? 'portal');
@endphp

@if($analyticsConfigured)
    <section data-analytics-consent class="mb-4 text-sm" aria-label="{{ ctrans('texts.analytics_preferences') }}">
        <script type="application/json">{!! json_encode($analytics, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        <div data-consent-notice hidden class="rounded-lg border border-gray-200 bg-white p-4">
            <p>{{ ctrans('texts.analytics_consent_message') }}
                <a class="underline" href="{{ config('ninja.privacy_policy_url.hosted') }}">{{ ctrans('texts.privacy_policy') }}</a>
            </p>
            <div class="mt-3 flex flex-col sm:flex-row gap-3">
                <button type="button" data-consent-choice="accepted" class="button button-secondary">{{ ctrans('texts.accept') }}</button>
                <button type="button" data-consent-choice="rejected" class="button button-secondary">{{ ctrans('texts.reject') }}</button>
            </div>
        </div>
        <button type="button" data-consent-preferences hidden class="button button-link">{{ ctrans('texts.analytics_preferences') }}</button>
    </section>
@endif
