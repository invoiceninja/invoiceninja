<script type="text/javascript">

function loadChart(data) {
    var ctx = document.getElementById('chart-canvas').getContext('2d');
    if (window.myChart) {
        window.myChart.config.data = data;
        window.myChart.config.options.scales.xAxes[0].time.unit = chartGroupBy.toLowerCase();
        window.myChart.config.options.scales.xAxes[0].time.round = chartGroupBy.toLowerCase();
        window.myChart.update();
    } else {
        $('#progress-div').hide();
        $('#chart-canvas').fadeIn();
        window.myChart = new Chart(ctx, {
            type: 'line',
            data: data,
            options: {
                tooltips: {
                    mode: 'x-axis',
                    titleFontSize: 15,
                    titleMarginBottom: 12,
                    bodyFontSize: 15,
                    bodySpacing: 10,
                    callbacks: {
                        title: function(item) {
                            return moment(item[0].xLabel).format("{{ $account->getMomentDateFormat() }}");
                        },
                        label: function(item, data) {
                            //return label + formatMoney(item.yLabel, chartCurrencyId, account.country_id);

                            /*
                            console.log('tooltip:');
                            console.log(item);
                            console.log(data);
                            */
                            return item.yLabel;
                        }
                    }
                },
                title: {
                    display: false,
                    fontSize: 18,
                    text: '{{ trans('texts.total_revenue') }}'
                },
                scales: {
                    xAxes: [{
                        type: 'time',
                        time: {
                            unit: chartGroupBy,
                            round: chartGroupBy,
                        },
                        gridLines: {
                            display: false,
                        },
                    }],
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function(label, index, labels) {
                                return roundSignificant(label);
                            }
                        },
                    }]
                }
            }
        });
    }
}

var account = {!! $account !!};
var chartGroupBy = "{{ $report->chartGroupBy() }}";

$(function() {
    var chartData = {!! json_encode($report->getChartData()) !!};
    //console.log(chartData);
    loadChart(chartData);
});

</script>

<div class="row">
    <div class="col-md-12">
        <canvas id="chart-canvas" style="background-color:white; padding:20px; width:100%; height: 250px;"></canvas>
    </div>
</div>

<p>&nbsp;</p>
