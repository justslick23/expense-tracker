
(function () {
    'use strict';

    function initialize() {
        const canvas = document.getElementById('historicalReportChart');
        const source = window.historicalReportData;

        if (!canvas || !source) return;

        if (typeof Chart === 'undefined') {
            console.warn('Chart.js is not loaded.');
            return;
        }

        const money = value => {
            return 'M ' + (Number(value) / 100).toLocaleString(
                'en-LS',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );
        };

        const theme = () => {
            const root = getComputedStyle(document.documentElement);

            const read = (property, fallback) =>
                root.getPropertyValue(property).trim() || fallback;

            return {
                text: read('--bs-body-color', '#64748b'),
                muted: read('--bs-secondary-color', '#94a3b8'),
                border: read('--bs-border-color', '#e2e8f0')
            };
        };

        const colors = theme();

        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: source.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: source.income,
                        backgroundColor: '#10b981',
                        borderRadius: 5,
                        maxBarThickness: 32
                    },
                    {
                        label: 'Expenses',
                        data: source.expenses,
                        backgroundColor: '#ef4444',
                        borderRadius: 5,
                        maxBarThickness: 32
                    },
                    {
                        label: 'Net cash flow',
                        data: source.cashFlow,
                        type: 'line',
                        borderColor: '#6366f1',
                        backgroundColor: '#6366f1',
                        tension: 0.3,
                        pointRadius: 4,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        labels: {
                            color: colors.text,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                return `${context.dataset.label}: ${money(context.parsed.y)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: colors.muted },
                        grid: { display: false }
                    },
                    y: {
                        ticks: {
                            color: colors.muted,
                            callback(value) {
                                return money(value);
                            }
                        },
                        grid: { color: colors.border }
                    }
                }
            }
        });

        const observer = new MutationObserver(() => {
            const colors = theme();

            chart.options.plugins.legend.labels.color = colors.text;
            chart.options.scales.x.ticks.color = colors.muted;
            chart.options.scales.y.ticks.color = colors.muted;
            chart.options.scales.y.grid.color = colors.border;

            chart.update('none');
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-bs-theme', 'data-theme', 'class']
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
