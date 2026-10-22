import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

// Colors with enough contrast on the glass panels in both themes.
function palette() {
    const dark = document.documentElement.classList.contains('dark');

    return dark
        ? { bar: '#818cf8', text: '#e5e7eb', grid: 'rgba(255, 255, 255, 0.12)' }
        : { bar: '#4338ca', text: '#1f2937', grid: 'rgba(15, 23, 42, 0.12)' };
}

function applyPalette(chart) {
    const colors = palette();
    chart.data.datasets[0].backgroundColor = colors.bar;
    for (const axis of ['x', 'y']) {
        chart.options.scales[axis].ticks.color = colors.text;
        chart.options.scales[axis].grid.color = colors.grid;
    }
}

// Data is already privacy-suppressed on the server: a suppressed count has value null
// (no bar) and its label carries the "<5" marker instead of the number.
document.querySelectorAll('canvas[data-report-chart]').forEach((canvas) => {
    const data = JSON.parse(canvas.dataset.reportChart);
    const labels = data.labels.map((label, index) => (data.values[index] === null ? `${label} (${data.display[index]})` : label));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const chart = new Chart(canvas, {
        type: 'bar',
        data: { labels, datasets: [{ data: data.values, borderRadius: 4, maxBarThickness: 20 }] },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 400 },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (context) => ` ${data.display[context.dataIndex]} students` } },
            },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 }, grid: {} },
                y: { ticks: { autoSkip: false }, grid: { display: false } },
            },
        },
    });

    applyPalette(chart);
    chart.update('none');

    // Follow the light/dark toggle.
    new MutationObserver(() => {
        applyPalette(chart);
        chart.update('none');
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
