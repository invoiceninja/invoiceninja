<script type="text/javascript">

function loadLineChart(data) {
    var ctx = document.getElementById('lineChartCanvas').getContext('2d');
    @if (! $report->isPieChartEnabled())
        document.getElementById('lineChartCanvas').height = 80;
    @endif
    new Chart(ctx, {
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
                        return item.yLabel;
                    }
                }
            },
            scales: {
                xAxes: [{
                    type: 'time',
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

function loadPieChart(data) {
    var ctx = document.getElementById('pieChartCanvas').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: data,
    });
}


@if ($report->isLineChartEnabled())
$(function() {
    var lineChartData = {!! json_encode($report->getLineChartData()) !!};
    loadLineChart(lineChartData);

    var pieChartData = {!! json_encode($report->getPieChartData()) !!};
    if (pieChartData) {
        loadPieChart(pieChartData);
    }
});
@endif

</script>

@if ($report->isLineChartEnabled())
<div class="row">
    <div class="col-md-{{ $report->isPieChartEnabled() ? 6 : 12 }}">
        <canvas id="lineChartCanvas" style="background-color:white; padding:20px; width:100%; height: 250px;"></canvas>
    </div>
    <div class="col-md-6" style="display:{{ $report->isPieChartEnabled() ? 'block' : 'none' }}">
        <canvas id="pieChartCanvas" style="background-color:white; padding:20px; width:100%; height: 250px;"></canvas>
    </div>
</div>

<p>&nbsp;</p>
@endif
