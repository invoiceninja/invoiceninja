@extends('layouts.ninja')
@section('meta_title', ctrans('texts.new_bank_account'))

@push('head')
<style type="text/css">
.enablebanking-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.aspsp-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.aspsp-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}

.aspsp-card:hover,
.aspsp-card:focus {
    border-color: #3A53EE;
    box-shadow: 0 0 10px rgba(58, 83, 238, 0.1);
    outline: none;
}

.aspsp-card:focus {
    box-shadow: 0 0 10px rgba(58, 83, 238, 0.3);
}

.aspsp-card.loading {
    cursor: wait;
    opacity: 0.8;
}

.aspsp-card.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 24px;
    height: 24px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-top: 3px solid #3A53EE;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    transform: translate(-50%, -50%);
}

.aspsp-card.inactive {
    opacity: 0.6;
    cursor: not-allowed;
}

.aspsp-card.inactive:hover {
    border-color: #ddd;
    box-shadow: none;
}

.aspsp-logo {
    width: 60px;
    height: 60px;
    object-fit: contain;
    margin-bottom: 10px;
}

.aspsp-name {
    font-weight: 600;
    margin-bottom: 5px;
}

.aspsp-country {
    font-size: 0.9em;
    color: #666;
    margin-bottom: 5px;
}

.aspsp-bic {
    font-size: 0.8em;
    color: #888;
    margin-bottom: 5px;
    font-family: monospace;
}

.aspsp-psu-types {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 10px;
}

.psu-type-badge {
    background-color: #f0f0f0;
    color: #666;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75em;
}

.psu-type-badge.hidden-type {
    background-color: #e0e0e0;
    color: #999;
    text-decoration: line-through;
}

.auth-approach-indicator {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background-color: #3A53EE;
    color: white;
    font-size: 0.6em;
    font-weight: bold;
    margin-left: 3px;
    vertical-align: middle;
    line-height: 16px;
    text-align: center;
}

.beta-badge {
    background-color: #FF9800;
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.7em;
    font-weight: bold;
    margin-left: 8px;
    vertical-align: middle;
}

.error-container {
    background-color: #fff8f8;
    border: 1px solid #ffebee;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    color: #d32f2f;
}

.btn-primary {
    background-color: #3A53EE;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}

.btn-primary:hover {
    background-color: #2a43d8;
}

.search-container {
    margin-bottom: 20px;
}

.search-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.filter-container {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-select {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
    flex: 1;
    min-width: 200px;
}

.inactive-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-weight: bold;
    color: #999;
}
</style>
@endpush

@section('body')

<div class="enablebanking-container">
    <div class="text-center mb-4">
        <img src="{{ ($account ?? false) && !$account->isPaid() ? asset('images/invoiceninja-black-logo-2.png') : (isset($company) && !is_null($company) ? $company->present()->logo() : asset('images/invoiceninja-black-logo-2.png')) }}" alt="Logo" style="max-height: 60px; margin-bottom: 20px;">
        
        <h2>{{ ($account ?? false) && !$account->isPaid() ? 'Invoice Ninja' : (isset($company) && !is_null($company) ? $company->name : 'Invoice Ninja') }}</h2>
        <p>{{ ctrans('texts.enablebanking_handler_subtitle', [], $lang ?? 'en') }}</p>
    </div>

    @if(isset($failed_reason))
        <div class="error-container">
            <h3>{{ ctrans('texts.enablebanking_handler_error_heading_unknown', [], $lang ?? 'en') }}</h3>
            <p>
                @if($failed_reason == 'token-invalid')
                    {{ ctrans('texts.enablebanking_handler_error_contents_token_invalid', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'account-config-invalid')
                    {{ ctrans('texts.enablebanking_handler_error_contents_account_config_invalid', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'not-available')
                    {{ ctrans('texts.enablebanking_handler_error_contents_not_available', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'aspsp-invalid')
                    {{ ctrans('texts.enablebanking_handler_error_contents_aspsp_invalid', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'auth-failure')
                    {{ ctrans('texts.enablebanking_handler_error_contents_auth_failure', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'ref-invalid')
                    {{ ctrans('texts.enablebanking_handler_error_contents_ref_invalid', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'session-invalid')
                    {{ ctrans('texts.enablebanking_handler_error_contents_session_invalid', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'session-creation-failed')
                    {{ ctrans('texts.enablebanking_handler_error_contents_session_creation_failed', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'accounts-retrieval-failed')
                    {{ ctrans('texts.enablebanking_handler_error_contents_accounts_retrieval_failed', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'session-no-accounts')
                    {{ ctrans('texts.enablebanking_handler_error_contents_session_no_accounts', [], $lang ?? 'en') }}
                @elseif($failed_reason == 'auth-code-missing')
                    {{ ctrans('texts.enablebanking_handler_error_contents_auth_code_missing', [], $lang ?? 'en') }}
                @else
                    {{ ctrans('texts.enablebanking_handler_error_contents_unknown', [], $lang ?? 'en') }}: {{ $failed_reason }}
                @endif
            </p>
            <div class="text-center mt-4">
                <a href="{{ $redirectUrl }}" class="btn-primary">
                    {{ ctrans('texts.enablebanking_handler_return', [], $lang ?? 'en') }}
                </a>
            </div>
        </div>
    @else
        @if(!isset($aspsp_name))
            <div class="filter-container">
                <input type="text" id="aspsp-search" class="search-input" placeholder="{{ ctrans('texts.enablebanking_search_placeholder', [], $lang ?? 'en') }}">
                
                <select id="country-filter" class="filter-select">
                    <option value="">{{ ctrans('texts.all_countries', [], $lang ?? 'en') }}</option>
                    @php
                        $countries = array_unique(array_column($aspsps, 'country'));
                        sort($countries);
                    @endphp
                    @foreach($countries as $country)
                        <option value="{{ $country }}">{{ $country }}</option>
                    @endforeach
                </select>
                
                <select id="psu-type-filter" class="filter-select">
                    <option value="">{{ ctrans('texts.all_account_types', [], $lang ?? 'en') }}</option>
                    <option value="personal">{{ ctrans('texts.personal', [], $lang ?? 'en') }}</option>
                    <option value="business">{{ ctrans('texts.business', [], $lang ?? 'en') }}</option>
                </select>
            </div>

            <div class="aspsp-list" id="aspsp-list">
                @foreach($aspsps as $aspsp)
                    @php
                        // Check if any auth method is hidden for the available psu_types
                        $isHidden = false;
                        if (!empty($aspsp['auth_methods']) && is_array($aspsp['auth_methods'])) {
                            foreach ($aspsp['auth_methods'] as $authMethod) {
                                if (!empty($authMethod['hidden_method']) && $authMethod['hidden_method']) {
                                    $isHidden = true;
                                    break;
                                }
                            }
                        }
                    @endphp
                    <div class="aspsp-card {{ $isHidden ? 'inactive' : '' }}" 
                         data-aspsp='@json($aspsp)'
                         tabindex="0"
                         onclick="{{ $isHidden ? 'event.stopPropagation()' : 'selectAspsp(this)' }}"
                         onkeydown="{{ $isHidden ? '' : 'handleAspspKeyDown(event, this)' }}">
                        @if($isHidden)
                            <div class="inactive-overlay">
                                {{ ctrans('texts.unavailable', [], $lang ?? 'en') }}
                            </div>
                        @endif
                        
                        <div class="aspsp-logo">
                            @if(!empty($aspsp['logo']))
                                <img src="{{ $aspsp['logo'] }}" alt="{{ $aspsp['name'] }} logo" class="aspsp-logo">
                            @else
                                <div style="width: 60px; height: 60px; background-color: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #666;">
                                    {{ strtoupper(substr($aspsp['name'], 0, 2)) }}
                                </div>
                            @endif
                        </div>
                        <div class="aspsp-name">
                            {{ $aspsp['name'] }}
                            @if(!empty($aspsp['beta']) && $aspsp['beta'])
                                <span class="beta-badge" title="{{ ctrans('texts.beta', [], $lang ?? 'en') }}">{{ ctrans('texts.beta', [], $lang ?? 'en') }}</span>
                            @endif
                        </div>
                        <div class="aspsp-country">{{ $aspsp['country'] }}</div>
                        @if(!empty($aspsp['bic']))
                            <div class="aspsp-bic">BIC: {{ $aspsp['bic'] }}</div>
                        @endif
                        @if(!empty($aspsp['psu_types']) && is_array($aspsp['psu_types']))
                            <div class="aspsp-psu-types">
                                @foreach($aspsp['psu_types'] as $psuType)
                                    @php
                                        // Check if this specific psu_type is hidden
                                        $typeIsHidden = false;
                                        $authApproach = null;
                                        if (!empty($aspsp['auth_methods']) && is_array($aspsp['auth_methods'])) {
                                            foreach ($aspsp['auth_methods'] as $authMethod) {
                                                if ($authMethod['psu_type'] === $psuType) {
                                                    if (!empty($authMethod['hidden_method']) && $authMethod['hidden_method']) {
                                                        $typeIsHidden = true;
                                                    }
                                                    if (!empty($authMethod['approach'])) {
                                                        $authApproach = strtolower($authMethod['approach']);
                                                    }
                                                    break;
                                                }
                                            }
                                        }
                                    @endphp
                                    <span class="psu-type-badge {{ $typeIsHidden ? 'hidden-type' : '' }}" title="{{ $authApproach ? ucfirst($authApproach) : '' }}">
                                        {{ ctrans('texts.'.$psuType, [], $lang ?? 'en') }}
                                        @if($authApproach)
                                            <span class="auth-approach-indicator" title="{{ ucfirst($authApproach) }}">
                                                {{ strtoupper(substr($authApproach, 0, 1)) }}
                                            </span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center">
                <h3>{{ ctrans('texts.enablebanking_connecting_to', [], $lang ?? 'en') }} {{ $aspsp_name }}</h3>
                <p>{{ ctrans('texts.enablebanking_redirect_message', [], $lang ?? 'en') }}</p>
                <div class="mt-4">
                    <div class="spinner" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #3A53EE; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                </div>
            </div>
        @endif
    @endif
</div>

@endsection

@push('footer')
<script>
    // Search and filter functionality
    function filterAspsps() {
        const searchTerm = document.getElementById('aspsp-search')?.value.toLowerCase() || '';
        const countryFilter = document.getElementById('country-filter')?.value || '';
        const psuTypeFilter = document.getElementById('psu-type-filter')?.value || '';
        
        const aspspCards = document.querySelectorAll('.aspsp-card');
        
        aspspCards.forEach(card => {
            const aspsp = JSON.parse(card.getAttribute('data-aspsp'));
            
            // Check search term
            const matchesSearch = 
                aspsp.name.toLowerCase().includes(searchTerm) ||
                aspsp.country.toLowerCase().includes(searchTerm) ||
                (aspsp.bic && aspsp.bic.toLowerCase().includes(searchTerm));
            
            // Check country filter
            const matchesCountry = !countryFilter || aspsp.country === countryFilter;
            
            // Check PSU type filter
            const psuTypes = aspsp.psu_types || [];
            const matchesPsuType = !psuTypeFilter || (Array.isArray(psuTypes) && psuTypes.includes(psuTypeFilter));
            
            // Check if ASPSP is hidden (hidden_method in auth_methods)
            let isHidden = false;
            if (aspsp.auth_methods && Array.isArray(aspsp.auth_methods)) {
                isHidden = aspsp.auth_methods.some(method => method.hidden_method);
            }
            
            // Show/hide based on all filters (also hide inactive ASPSPs)
            card.style.display = (matchesSearch && matchesCountry && matchesPsuType && !isHidden) ? 'block' : 'none';
        });
    }

    // Event listeners for filters
    document.getElementById('aspsp-search')?.addEventListener('input', filterAspsps);
    document.getElementById('country-filter')?.addEventListener('change', filterAspsps);
    document.getElementById('psu-type-filter')?.addEventListener('change', filterAspsps);

    // ASPSP selection
    function selectAspsp(card) {
        // Add loading state
        card.classList.add('loading');
        
        const aspsp = JSON.parse(card.getAttribute('data-aspsp'));
        const url = new URL(window.location.href);
        url.searchParams.set('aspsp_name', aspsp.name);
        url.searchParams.set('aspsp_country', aspsp.country);
        window.location.href = url.href;
    }
    
    // Keyboard navigation for ASPSP cards
    function handleAspspKeyDown(event, card) {
        // Space or Enter key
        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            selectAspsp(card);
        }
    }

    // Animation for spinner
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
</script>
@endpush