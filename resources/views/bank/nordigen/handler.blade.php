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
        // Redirect URL that is being used when modal is being closed.
        redirectUrl: "{{ $redirectUrl }}" || new URL("", window.location.origin).href,
        // Text that will be displayed on the left side under the logo. Text is limited to 100 characters, and rest will be truncated. @turbo124 replace with a translated version like ctrans()
        text: "{{ ($account ?? false) && !$account->isPaid() ? 'Invoice Ninja' : (isset($company) && !is_null($company) ? $company->name : 'Invoice Ninja') }} will gain access for your selected bank account. After selecting your institution you are redirected to theire front-page to complete the request with your account credentials.",
        // Logo URL that will be shown below the modal form.
        logoUrl: "{{ ($account ?? false) && !$account->isPaid() ? asset('images/invoiceninja-black-logo-2.png') : (isset($company) && !is_null($company) ? $company->present()->logo() : asset('images/invoiceninja-black-logo-2.png')) }}",
        // Will display country list with corresponding institutions. When `countryFilter` is set to `false`, only list of institutions will be shown.
        countryFilter: false,
        // style configs
        styles: {
            // Primary
            // Link to google font
            fontFamily: new URL("assets/fonts/Roboto-Regular.ttf", window.location.origin).href,
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

    const failedReason = "{{ $failed_reason ?? '' }}".trim();

    new institutionSelector(@json($institutions ?? []), 'institution-modal-content', config);

    if (!failedReason) {

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
    } else {
        document.getElementsByClassName("institution-search-container")[0].remove();
        document.getElementsByClassName("institution-container")[0].remove();

        const heading = document.querySelectorAll('h2')[0];
        const wrapper = document.getElementById("institution-modal-content");
        const contents = document.createElement("div");
        contents.id = "failed-container";
        contents.className = "mt-2";
        contents.style["font-size"] = "80%";
        contents.style["opacity"] = "80%";

        let restartFlow = false; // return, restart, refresh
        heading.innerHTML = "An Error has occured";
        contents.innerHTML = "An unknown Error has occured! Reason: " + failedReason;
        switch (failedReason) {
            // Connect Screen Errors
            case "token-invalid":
                heading.innerHTML = "Invalid Token";
                contents.innerHTML = "The provided token was invalid. Please restart the flow, with a valid one_time_token. Contact support for help, if this issue persists.";
                break;
            case "account-config-invalid":
                heading.innerHTML = "Missing Credentials";
                contents.innerHTML = "The provided credentials for nordigen are eighter missing or invalid. Contact support for help, if this issue persists.";
                break;
            case "not-available":
                heading.innerHTML = "Not Available";
                contents.innerHTML = "This flow is not available for your account. Considder upgrading to enterprise version. Contact support for help, if this issue persists.";
                break;
            case "institution-invalid":
                restartFlow = true;
                heading.innerHTML = "Invalid Institution";
                contents.innerHTML = "The provided institution-id is invalid or no longer valid. You can go to the bank selection page by clicking the button below or cancel the flow by clicking on the 'X' above.";
                break;
            // Confirm Screen Errors
            case "ref-invalid":
                heading.innerHTML = "Invalid Reference";
                contents.innerHTML = "Nordigen did not provide a valid reference. Please run flow again and contact support, if this issue persists.";
                break;
            case "requisition-not-found":
                heading.innerHTML = "Invalid Requisition";
                contents.innerHTML = "Nordigen did not provide a valid reference. Please run flow again and contact support, if this issue persists.";
                break;
            case "requisition-invalid-status":
                heading.innerHTML = "Not Ready";
                contents.innerHTML = "You may called this site to early. Please finish authorization and refresh this page. Contact support for help, if this issue persists.";
                break;
            case "requisition-no-accounts":
                heading.innerHTML = "No Accounts selected";
                contents.innerHTML = "The service has not returned any valid accounts. Considder restarting the flow.";
                break;
            case "unknown":
                break;
            default:
                console.warn('Invalid or missing failed_reason code: ' + failedReason);
                break;

        }
        wrapper.appendChild(contents);

        const restartUrl = new URL(window.location.pathname, window.location.origin); // no searchParams
        const returnButton = document.createElement('div');
        returnButton.className = "mt-4";
        returnButton.innerHTML = `<a class="button button-primary bg-blue-600 my-4" href="${restartFlow ? restartUrl.href : config.redirectUrl}">${restartFlow ? 'Restart flow.' : 'Return to application.'}</a>`
        wrapper.appendChild(returnButton);
    }

</script>

@endpush