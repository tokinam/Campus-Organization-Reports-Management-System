import Chart from 'chart.js/auto';

Chart.defaults.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 280;
Chart.defaults.animation.easing = 'easeOutQuart';

function readChartTheme() {
    const styles = window.getComputedStyle(document.body);

    return {
        text: styles.getPropertyValue('--orgtrack-ink').trim() || '#0f172a',
        muted: styles.getPropertyValue('--orgtrack-muted').trim() || '#64748b',
        grid: styles.getPropertyValue('--orgtrack-line').trim() || '#e2e8f0',
        surface: styles.getPropertyValue('--orgtrack-surface').trim() || '#ffffff',
    };
}

const initialTheme = readChartTheme();
Chart.defaults.color = initialTheme.text;
Chart.defaults.borderColor = initialTheme.grid;

const palette = {
    Pending: '#F59E0B',
    Ongoing: '#0EA5E9',
    Completed: '#10B981',
    Archived: '#CBD5E1',
    Aligned: '#15803D',
    'Partially Aligned': '#D97706',
    'Not Aligned': '#E11D48',
};

const centerTextPlugin = {
    id: 'monitoringCenterText',
    afterDraw(chart) {
        const text = chart.canvas.dataset.centerText;
        if (!text) return;

        const { ctx, chartArea } = chart;
        if (!chartArea) return;

        ctx.save();
        ctx.font = '700 24px ui-sans-serif, system-ui, sans-serif';
        ctx.fillStyle = readChartTheme().text;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2);
        ctx.restore();
    },
};

function readChartData(canvas) {
    return JSON.parse(canvas.dataset.chartData || '{}');
}

function initDoughnut(canvas) {
    const data = readChartData(canvas);
    const labels = data.labels || [];
    const values = data.values || [];
    const hasValues = values.some(value => Number(value) > 0);
    const total = values.reduce((sum, value) => sum + Number(value || 0), 0);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: hasValues ? values : [1],
                backgroundColor: hasValues ? labels.map(label => palette[label] || '#CBD5E1') : ['#e2e8f0'],
                borderColor: readChartTheme().surface,
                borderWidth: 2,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '76%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: hasValues,
                    callbacks: {
                        label: context => {
                            const count = Number(context.raw || 0);
                            const percentage = total > 0 ? (count / total * 100).toFixed(1) : '0.0';
                            return `${context.label}: ${count} (${percentage}%)`;
                        },
                    },
                },
            },
        },
        plugins: [centerTextPlugin],
    });
}

function initOrganizationProgress(canvas) {
    const data = readChartData(canvas);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Completion',
                data: data.values || [],
                backgroundColor: '#10b981',
                borderRadius: 5,
                barThickness: 14,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { color: readChartTheme().muted, callback: value => `${value}%` },
                    grid: { color: readChartTheme().grid },
                },
                y: { ticks: { color: readChartTheme().muted }, grid: { display: false } },
            },
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => `${context.raw}% complete` } } },
        },
    });
}

function applyMonitoringChartTheme() {
    const theme = readChartTheme();
    Chart.defaults.color = theme.text;
    Chart.defaults.borderColor = theme.grid;

    document.querySelectorAll('canvas').forEach(canvas => {
        const chart = Chart.getChart(canvas);
        if (!chart) return;

        chart.options.color = theme.text;
        if (chart.options.scales?.x) {
            chart.options.scales.x.ticks.color = theme.muted;
            if (chart.options.scales.x.grid.display !== false) {
                chart.options.scales.x.grid.color = theme.grid;
            }
        }
        if (chart.options.scales?.y) {
            chart.options.scales.y.ticks.color = theme.muted;
            if (chart.options.scales.y.grid.display !== false) {
                chart.options.scales.y.grid.color = theme.grid;
            }
        }
        if (chart.config.type === 'doughnut') {
            chart.data.datasets.forEach(dataset => {
                dataset.borderColor = theme.surface;
            });
        }
        chart.update('none');
    });
}

let chartThemeObserver;

function initializeMonitoringCharts() {
    document.querySelectorAll('canvas[data-chart-type="doughnut"]').forEach(initDoughnut);
    document.querySelectorAll('canvas[data-chart-type="organization-progress"]').forEach(initOrganizationProgress);
    applyMonitoringChartTheme();

    if (!chartThemeObserver) {
        chartThemeObserver = new MutationObserver(applyMonitoringChartTheme);
        chartThemeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme'],
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMonitoringCharts, { once: true });
} else {
    initializeMonitoringCharts();
}