@extends('layouts.ninja')
@section('meta_title', ctrans('texts.new_bank_account'))

@push('head')

<link href="https://unpkg.com/nordigen-bank-ui@1.5.2/package/src/selector.min.css" rel="stylesheet" />

@endpush

@section('body')

<div id="institution-content-wrapper"></div>

@endsection

@push('footer')

<script type='text/javascript' src='https://unpkg.com/nordigen-bank-ui@1.5.2/package/src/selector.min.js'></script>

<script>


    // Pass your redirect link after user has been authorized in institution
    const config = {
        // Text that will be displayed on the left side under the logo. Text is limited to 100 characters, and rest will be truncated.
        text: "Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean mavdvd",
        // Logo URL that will be shown below the modal form.
        logoUrl: "{{ $account && !$account->isPaid() ? asset('images/invoiceninja-black-logo-2.png') : (isset($company) && !is_null($company) ? $company->present()->logo() : '') }}",
        // Will display country list with corresponding institutions. When `countryFilter` is set to `false`, only list of institutions will be shown.
        countryFilter: false,
        // style configs
        styles: {
            // Primary
            // Link to google font
            fontFamily: 'https://fonts.googleapis.com/css2?family=Roboto&display=swap', // @todo replace to match german law: not use google fonts and use local instead
            fontSize: '15',
            backgroundColor: '#F2F2F2',
            textColor: '#222',
            headingColor: '#222',
            linkColor: '#8d9090',
            // Modal
            modalTextColor: '#1B2021',
            modalBackgroundColor: '#fff',
            // Button
            buttonColor: '#3A53EE',
            buttonTextColor: '#fff'
        }
    };


    new institutionSelector(@json($institutions), 'institution-modal-content', config);

    const institutionList = Array.from(document.querySelectorAll('.ob-list-institution > a'));

    institutionList.forEach((institution) => {
        institution.addEventListener('click', (e) => {
            e.preventDefault()
            const institutionId = institution.getAttribute('data-institution');
            const url = new URL(window.location.href);
            url.searchParams.set('institution_id', institutionId);
            window.location.href = url.href;
        });
    });

</script>

@endpush