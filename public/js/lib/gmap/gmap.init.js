(function($){

var map;
$(document).ready(function(){
    map = new GMaps({
        el: '#basic-map',
        lat: -12.043333,
        lng: -77.028333,
        zoomControl : true,
        zoomControlOpt: {
            style : 'SMALL',
            position: 'TOP_LEFT'
        },
        panControl : false,
        streetViewControl : false,
        mapTypeControl: false,
        overviewMapControl: false
    });
});




var map, infoWindow;
$(document).ready(function(){
    infoWindow = new google.maps.InfoWindow({});
    map = new GMaps({
        el: '#map-2',
        zoom: 11,
        lat: 41.850033,
        lng: -87.6500523
    });
    map.loadFromFusionTables({
        query: {
            select: '\'Geocodable address\'',
            from: '1mZ53Z70NsChnBMm-qEYmSDOvLXgrreLTkQUvvg'
        },
        suppressInfoWindows: true,
        events: {
            click: function(point){
                infoWindow.setContent('You clicked here!');
                infoWindow.setPosition(point.latLng);
                infoWindow.open(map.map);
            }
        }
    });
});




var map, rectangle, polygon, circle;
$(document).ready(function(){
    map = new GMaps({
        el: '#map-3',
        lat: -12.043333,
        lng: -77.028333
    });
    var bounds = [[-12.030397656836609,-77.02373871559225],[-12.034804866577001,-77.01154422636042]];
    rectangle = map.drawRectangle({
        bounds: bounds,
        strokeColor: '#BBD8E9',
        strokeOpacity: 1,
        strokeWeight: 3,
        fillColor: '#BBD8E9',
        fillOpacity: 0.6
    });

    var paths = [[-12.040397656836609,-77.03373871559225],[-12.040248585302038,-77.03993927003302],[-12.050047116528843,-77.02448169303511],[-12.044804866577001,-77.02154422636042]];
    polygon = map.drawPolygon({
        paths: paths,
        strokeColor: '#25D359',
        strokeOpacity: 1,
        strokeWeight: 3,
        fillColor: '#25D359',
        fillOpacity: 0.6
    });
    var lat = -12.040504866577001;
    var lng = -77.02024422636042;
    circle = map.drawCircle({
        lat: lat,
        lng: lng,
        radius: 350,
        strokeColor: '#432070',
        strokeOpacity: 1,
        strokeWeight: 3,
        fillColor: '#432070',
        fillOpacity: 0.6
    });
    for(var i in paths){
        bounds.push(paths[i]);
    }
    var b = [];
    for(var i in bounds){
        latlng = new google.maps.LatLng(bounds[i][0], bounds[i][1]);
        b.push(latlng);
    }
    for(var i in paths){
        latlng = new google.maps.LatLng(paths[i][0], paths[i][1]);
        b.push(latlng);
    }
    map.fitLatLngBounds(b);
});






var map;
$(document).ready(function(){
    map = new GMaps({
        el: '#map-4',
        lat: -12.043333,
        lng: -77.028333
    });
    //locations request
    map.getElevations({
        locations : [[-12.040397656836609,-77.03373871559225], [-12.050047116528843,-77.02448169303511],  [-12.044804866577001,-77.02154422636042]],
        callback : function (result, status){
            if (status == google.maps.ElevationStatus.OK) {
                for (var i in result){
                    map.addMarker({
                        lat: result[i].location.lat(),
                        lng: result[i].location.lng(),
                        title: 'Marker with InfoWindow',
                        infoWindow: {
                            content: '<p>The elevation is '+result[i].elevation+' in meters</p>'
                        }
                    });
                }
            }
        }
    });
});
















var map;
$(document).ready(function(){
    var map = new GMaps({
        el: '#map-5',
        lat: -12.043333,
        lng: -77.028333
    });

    GMaps.geolocate({
        success: function(position){
            map.setCenter(position.coords.latitude, position.coords.longitude);
        },
        error: function(error){
            alert('Geolocation failed: '+error.message);
        },
        not_supported: function(){
            alert("Your browser does not support geolocation");
        },
        always: function(){
            alert("Done!");
        }
    });
});











var map, infoWindow;
$(document).ready(function(){
    infoWindow = new google.maps.InfoWindow({});
    map = new GMaps({
        el: '#map-6',
        zoom: 12,
        lat: 40.65,
        lng: -73.95
    });
    map.loadFromKML({
        url: 'https://api.flickr.com/services/feeds/geo/?g=322338@N20&lang=en-us&format=feed-georss',
        suppressInfoWindows: true,
        events: {
            click: function(point){
                infoWindow.setContent(point.featureData.infoWindowHtml);
                infoWindow.setPosition(point.latLng);
                infoWindow.open(map.map);
            }
        }
    });
});





var map;
$(function () {
    map = new GMaps({
        el: "#map-7",
        lat: -12.043333,
        lng: -77.028333,
        zoom: 3
    });

    map.addLayer('weather', {
        clickable: false
    });
    map.addLayer('clouds');
});






map = new GMaps({
    el: '#map-8',
    zoom: 16,
    lat: -12.043333,
    lng: -77.028333,
    click: function(e){
        alert('click');
    },
    dragend: function(e){
        alert('dragend');
    }
});


})(jQuery);
