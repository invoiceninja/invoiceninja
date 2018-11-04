<div id="map"></div>

<script type="text/javascript">
    function initialize() {
        var mapCanvas = document.getElementById('map');
        var mapOptions = {
            zoom: 1,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            zoomControl: true,
        };

        var map = new google.maps.Map(mapCanvas, mapOptions)
        var address = {!! json_encode(e("{$location->address1} {$location->address2} {$location->city} {$location->state} {$location->postal_code} " . ($location->country ? $location->country->getName() : ''))) !!};

        geocoder = new google.maps.Geocoder();
        geocoder.geocode( { 'address': address}, function(results, status) {
            if (status == google.maps.GeocoderStatus.OK) {
                if (status != google.maps.GeocoderStatus.ZERO_RESULTS) {
                    var result = results[0];
                    map.setCenter(result.geometry.location);

                    var infowindow = new google.maps.InfoWindow(
                            { content: '<b>'+result.formatted_address+'</b>',
                                size: new google.maps.Size(150, 50)
                            });

                    var marker = new google.maps.Marker({
                        position: result.geometry.location,
                        map: map,
                        title:address,
                    });
                    google.maps.event.addListener(marker, 'click', function() {
                        infowindow.open(map, marker);
                    });
                } else {
                    $('#map').hide();
                }
            } else {
                $('#map').hide();
            }
        });
    }

    google.maps.event.addDomListener(window, 'load', initialize);
</script>