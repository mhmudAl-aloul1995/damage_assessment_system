new ApexCharts(document.querySelector('#{{ $pie['id'] }}'), {
    series: @json($pie['series']),
    chart: {
        type: 'pie',
        height: 235,
        toolbar: { show: false },
        animations: { enabled: true }
    },
    labels: @json($pie['labels']),
    colors: ['#8CC36B', '#FF8F95'],
    legend: { show: false },
    stroke: {
        width: 3,
        colors: ['#ffffff']
    },
    dataLabels: {
        enabled: false
    },
    tooltip: {
        y: {
            formatter: function (value) {
                return value + ' buildings';
            }
        }
    },
    plotOptions: {
        pie: {
            expandOnClick: false
        }
    }
}).render();
