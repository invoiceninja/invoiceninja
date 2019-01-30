@if (Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS))
    @if ($customLabel = $account->customLabel($entityType . '1'))
        @include('partials.custom_field', [
            'field' => 'custom_value1',
            'label' => $customLabel
        ])
    @endif
    @if ($customLabel = $account->customLabel($entityType . '2'))
        @include('partials.custom_field', [
            'field' => 'custom_value2',
            'label' => $customLabel
        ])
    @endif
@endif
