
<script type="text/javascript">
function initMap() {
    var locations = {!! $locations !!}

    var maps = [];
    for (var i = 0; i < locations.length; i++) {
        console.log(locations[i][1]);


        var map = new google.maps.Map(document.getElementById('map' + locations[i].id ), {
          zoom: 8,
          center: {lat: -34.397, lng: 150.644}
        });


        var geocoder = new google.maps.Geocoder();
        var address = locations[i].address1 + ' ' + locations[i].address2+ ' ' + locations[i].city + ' ' + locations[i].state + ' ' + locations[i].postal_code + ' ' + locations[i].country;
        geocodeAddress(geocoder, map + locations[i], address);

    };
}

initMap();

//google.maps.event.addDomListener(window, 'load', initMap);

      function geocodeAddress(geocoder, resultsMap, address) {

        geocoder.geocode({'address': address}, function(results, status) {
          if (status === 'OK') {
            resultsMap.setCenter(results[0].geometry.location);
            var marker = new google.maps.Marker({
              map: resultsMap,
              position: results[0].geometry.location
            });
          } else {
            alert('Geocode was not successful for the following reason: ' + status);
          }

        });

      }
</script>


