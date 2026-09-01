import Chart from 'chart.js/auto';

const canvas = document.getElementById('response-time-chart');
const payload = document.getElementById('status-chart-data');

if (canvas instanceof HTMLCanvasElement && payload) {
    const chart = JSON.parse(payload.textContent || '{}');
    const labels = chart.labels ?? [];
    const responseTimes = chart.response_times ?? [];
    const success = chart.success ?? [];
    const pointColors = success.map((ok) => (ok ? 'rgb(16, 185, 129)' : 'rgb(239, 68, 68)'));

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Tempo risposta (ms)',
                    data: responseTimes,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    pointBackgroundColor: pointColors,
                    pointBorderColor: pointColors,
                    pointRadius: 4,
                    tension: 0.25,
                    fill: true,
                    spanGaps: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const ms = context.parsed.y;
                            if (ms === null) {
                                return 'Non disponibile';
                            }

                            return ms + ' ms';
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'ms' },
                },
            },
        },
    });
}
