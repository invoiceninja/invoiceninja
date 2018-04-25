<script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}"></script>
<script type="text/javascript">

var countries = {!! \Cache::get('countries') !!};
var countryMap = {};
for (var i=0; i<countries.length; i++) {
    var country = countries[i];
    countryMap[country.id] = country;
}

$(function() {
    showGeocodePlaceholder();
    showGeocodePlaceholder(true);

    $('#billing_address').change(function() {
        showGeocodePlaceholder();
    });
    $('#shipping_address').change(function() {
        showGeocodePlaceholder(true);
    });
})

function showGeocodePlaceholder(isShipping) {
    var postalCodeField = 'postal_code';
    if (isShipping) {
        postalCodeField = 'shipping_' + postalCodeField;
    }
    var placeholder = hasCityOrState(isShipping) ? '' : {!! json_encode(trans('texts.autofills_city_state')) !!};
    $('#' + postalCodeField).attr('placeholder', placeholder);
}

function hasCityOrState(isShipping) {
    var cityField = 'city';
    var stateField = 'state';

    if (isShipping) {
        cityField = 'shipping_' + cityField;
        stateField = 'shipping_' + stateField;
    }

    if ($('#' + cityField).val() || $('#' + stateField).val()) {
        return true;
    }
}

function lookupPostalCode(isShipping) {
    var cityField = 'city';
    var stateField = 'state';
    var postalCodeField = 'postal_code';
    var countryField = 'country_id';

    if (isShipping) {
        cityField = 'shipping_' + cityField;
        stateField = 'shipping_' + stateField;
        postalCodeField = 'shipping_' + postalCodeField;
        countryField = 'shipping_' + countryField;
    }

    if (hasCityOrState(isShipping)) {
        return;
    }

    var postalCode = $('#' + postalCodeField).val();
    var countryId = $('#' + countryField).val() || {{ $account->getCountryId() }};
    var countryCode = countryMap[countryId].iso_3166_2;

    if (! postalCode || postalCode.length < 5) {
        return;
    }

    $('#' + cityField).attr('placeholder', {!! json_encode(trans('texts.loading') . '...') !!});

    var geocoder = new google.maps.Geocoder;
    geocoder.geocode({
        componentRestrictions: {
            country: countryCode,
            postalCode: postalCode,
        }
    }, function(results, status) {
        if (status == 'OK') {
            if (! results.length) {
                return;
            }

            //console.log('Address: ' + results[0].formatted_address);
            var components = results[0].address_components;
            for (var i=0; i<components.length; i++) {
                //console.log(component);
                var component = components[i];
                if (component.types.indexOf('locality') >= 0
                || component.types.indexOf('neighborhood') >= 0) {
                    if (! $('#' + cityField).val()) {
                        $('#' + cityField).val(component.long_name);
                    }
                } else if (component.types.indexOf('administrative_area_level_1') >= 0
                || component.types.indexOf('postal_town') >= 0) {
                    if (! $('#' + stateField).val()) {
                        $('#' + stateField).val(component.short_name);
                    }
                }
            }
            $('#' + cityField).attr('placeholder', '');
        } else {
            $('#' + cityField).attr('placeholder', {!! json_encode(trans('texts.no_match_found')) !!});
        }
        showGeocodePlaceholder(isShipping);
    });
}

</script>
