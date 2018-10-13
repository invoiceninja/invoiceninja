
(function($){

 "use strict"; // Start of use strict

 var SufeeAdmin = {

    cpuLoad: function(){

        var data = [],
            totalPoints = 300;

        function getRandomData() {

            if ( data.length > 0 )
                data = data.slice( 1 );

            // Do a random walk

            while ( data.length < totalPoints ) {

                var prev = data.length > 0 ? data[ data.length - 1 ] : 50,
                    y = prev + Math.random() * 10 - 5;

                if ( y < 0 ) {
                    y = 0;
                } else if ( y > 100 ) {
                    y = 100;
                }

                data.push( y );
            }

            // Zip the generated y values with the x values

            var res = [];
            for ( var i = 0; i < data.length; ++i ) {
                res.push( [ i, data[ i ] ] )
            }

            return res;
        }

        // Set up the control widget

        var updateInterval = 30;
        $( "#updateInterval" ).val( updateInterval ).change( function () {
            var v = $( this ).val();
            if ( v && !isNaN( +v ) ) {
                updateInterval = +v;
                if ( updateInterval < 1 ) {
                    updateInterval = 1;
                } else if ( updateInterval > 3000 ) {
                    updateInterval = 3000;
                }
                $( this ).val( "" + updateInterval );
            }
        } );

        var plot = $.plot( "#cpu-load", [ getRandomData() ], {
            series: {
                shadowSize: 0 // Drawing is faster without shadows
            },
            yaxis: {
                min: 0,
                max: 100
            },
            xaxis: {
                show: false
            },
            colors: [ "#007BFF" ],
            grid: {
                color: "transparent",
                hoverable: true,
                borderWidth: 0,
                backgroundColor: 'transparent'
            },
            tooltip: true,
            tooltipOpts: {
                content: "Y: %y",
                defaultTheme: false
            }


        } );

        function update() {

            plot.setData( [ getRandomData() ] );

            // Since the axes don't change, we don't need to call plot.setupGrid()

            plot.draw();
            setTimeout( update, updateInterval );
        }

        update();

    },

    lineFlot: function(){

        var sin = [],
            cos = [];

        for ( var i = 0; i < 10; i += 0.1 ) {
            sin.push( [ i, Math.sin( i ) ] );
            cos.push( [ i, Math.cos( i ) ] );
        }

        var plot = $.plot( "#flot-line", [
            {
                data: sin,
                label: "sin(x)"
            },
            {
                data: cos,
                label: "cos(x)"
            }
            ], {
            series: {
                lines: {
                    show: true
                },
                points: {
                    show: true
                }
            },
            yaxis: {
                min: -1.2,
                max: 1.2
            },
            colors: [ "#007BFF", "#DC3545" ],
            grid: {
                color: "#fff",
                hoverable: true,
                borderWidth: 0,
                backgroundColor: 'transparent'
            },
            tooltip: true,
            tooltipOpts: {
                content: "'%s' of %x.1 is %y.4",
                shifts: {
                    x: -60,
                    y: 25
                }
            }
        } );
    },

    pieFlot: function(){

        var data = [
            {
                label: "Primary",
                data: 1,
                color: "#8fc9fb"
            },
            {
                label: "Success",
                data: 3,
                color: "#007BFF"
            },
            {
                label: "Danger",
                data: 9,
                color: "#19A9D5"
            },
            {
                label: "Warning",
                data: 20,
                color: "#DC3545"
            }
        ];

        var plotObj = $.plot( $( "#flot-pie" ), data, {
            series: {
                pie: {
                    show: true,
                    radius: 1,
                    label: {
                        show: false,

                    }
                }
            },
            grid: {
                hoverable: true
            },
            tooltip: {
                show: true,
                content: "%p.0%, %s, n=%n", // show percentages, rounding to 2 decimal places
                shifts: {
                    x: 20,
                    y: 0
                },
                defaultTheme: false
            }
        } );
    },

    line2Flot: function(){

        // first chart
        var chart1Options = {
            series: {
                lines: {
                    show: true
                },
                points: {
                    show: true
                }
            },
            xaxis: {
                mode: "time",
                timeformat: "%m/%d",
                minTickSize: [ 1, "day" ]
            },
            grid: {
                hoverable: true
            },
            legend: {
                show: false
            },
            grid: {
                color: "#fff",
                hoverable: true,
                borderWidth: 0,
                backgroundColor: 'transparent'
            },
            tooltip: {
                show: true,
                content: "y: %y"
            }
        };
        var chart1Data = {
            label: "chart1",
            color: "#007BFF",
            data: [
          [ 1354521600000, 6322 ],
          [ 1355040000000, 6360 ],
          [ 1355223600000, 6368 ],
          [ 1355306400000, 6374 ],
          [ 1355487300000, 6388 ],
          [ 1355571900000, 6393 ]
        ]
        };
        $.plot( $( "#chart1" ), [ chart1Data ], chart1Options );
    },

    barFlot: function(){

        // second chart
        var flotBarOptions = {
            series: {
                bars: {
                    show: true,
                    barWidth: 43200000
                }
            },
            xaxis: {
                mode: "time",
                timeformat: "%m/%d",
                minTickSize: [ 1, "day" ]
            },
            grid: {
                hoverable: true
            },
            legend: {
                show: false
            },
            grid: {
                color: "#fff",
                hoverable: true,
                borderWidth: 0,
                backgroundColor: 'transparent'
            },
            tooltip: {
                show: true,
                content: "x: %x, y: %y"
            }
        };
        var flotBarData = {
            label: "flotBar",
            color: "#007BFF",
            data: [
          [ 1354521600000, 1000 ],
          [ 1355040000000, 2000 ],
          [ 1355223600000, 3000 ],
          [ 1355306400000, 4000 ],
          [ 1355487300000, 5000 ],
          [ 1355571900000, 6000 ]
        ]
        };
        $.plot( $( "#flotBar" ), [ flotBarData ], flotBarOptions );

    },

    plotting: function(){

        var d1 = [ [ 20, 20 ], [ 42, 60 ], [ 54, 20 ], [ 80, 80 ] ];

        //flot options
        var options = {
            legend: {
                show: false
            },
            series: {
                label: "Curved Lines Test",
                curvedLines: {
                    active: true,
                    nrSplinePoints: 20
                }
            },

            grid: {
                color: "#fff",
                hoverable: true,
                borderWidth: 0,
                backgroundColor: 'transparent'
            },
            tooltip: {
                show: true,
                content: "%s | x: %x; y: %y"
            },
            yaxes: [ {
                min: 10,
                max: 90
            }, {
                position: 'right'
            } ]
        };

        //plotting
        $.plot( $( "#flotCurve" ), [
            {
                data: d1,
                lines: {
                    show: true,
                    fill: true,
                    fillColor: "rgba(0,123,255,.15)",
                    lineWidth: 3
                },
                //curve the line  (old pre 1.0.0 plotting function)
                curvedLines: {
                    apply: true,
                    show: true,
                    fill: true,
                    fillColor: "rgba(0,123,255,.15)",

                }
          }, {
                data: d1,
                points: {
                    show: true,
                    fill: true,
                    fillColor: "rgba(0,123,255,.15)",
                }
          }
          ], options );
    }

};

$(document).ready(function() {
    SufeeAdmin.cpuLoad();
    SufeeAdmin.lineFlot();
    SufeeAdmin.pieFlot();
    SufeeAdmin.line2Flot();
    SufeeAdmin.barFlot();
    SufeeAdmin.plotting();

});

})(jQuery);
