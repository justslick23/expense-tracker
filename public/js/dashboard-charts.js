
(function () {
    'use strict';

    const data = window.dashboardChartData;

    if (!data || typeof Chart === 'undefined') {
        return;
    }

    const instances = [];

    const palette = [
        '#2563eb',
        '#10b981',
        '#f59e0b',
        '#8b5cf6',
        '#ef4444',
        '#06b6d4',
        '#ec4899',
        '#84cc16',
        '#6366f1',
        '#f97316'
    ];

    function minorToMajor(value) {
        return Number(value) / 100;
    }

    function money(value) {
        return 'M ' + minorToMajor(value).toLocaleString('en-LS', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function themeColors() {
        const styles = getComputedStyle(document.documentElement);

        const read = (name, fallback) =>
            styles.getPropertyValue(name).trim() || fallback;

        return {
            text: read('--bs-body-color', '#64748b'),
            muted: read('--bs-secondary-color', '#94a3b8'),
            border: read('--bs-border-color', '#e2e8f0'),
            income: '#10b981',
            expense: '#ef4444',
            budget: '#3b82f6'
        };
    }

    function commonOptions() {
        const theme = themeColors();

        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    labels: {
                        color: theme.text,
                        usePointStyle: true,
                        padding: 18,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            // For horizontal charts the monetary value
                            // is on the X axis rather than the Y axis.
                            const value = context.chart.options.indexAxis === 'y'
                                ? context.parsed.x
                                : context.parsed.y;

                            return `${context.dataset.label || context.label}: ${money(value ?? 0)}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: theme.muted
                    },
                    grid: {
                        display: false
                    },
                    border: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: theme.muted,
                        callback(value) {
                            return 'M ' + minorToMajor(value)
                                .toLocaleString('en-LS');
                        }
                    },
                    grid: {
                        color: theme.border
                    },
                    border: {
                        display: false,
                        dash: [4, 4]
                    }
                }
            }
        };
    }

    function addChart(canvas, config) {
        if (!canvas) {
            return;
        }

        const chart = new Chart(canvas, config);

        instances.push(chart);
    }

    /*
    |--------------------------------------------------------------------------
    | Income vs Expenses
    |--------------------------------------------------------------------------
    */

    function incomeExpenseChart() {
        const canvas = document.getElementById('incomeExpenseChart');

        if (!canvas || !data.income_expenses) {
            return;
        }

        const source = data.income_expenses;
        const theme = themeColors();

        addChart(canvas, {
            type: 'bar',
            data: {
                labels: source.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: source.income,
                        backgroundColor: theme.income,
                        borderRadius: 6,
                        maxBarThickness: 32
                    },
                    {
                        label: 'Expenses',
                        data: source.expenses,
                        backgroundColor: theme.expense,
                        borderRadius: 6,
                        maxBarThickness: 32
                    }
                ]
            },
            options: commonOptions()
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Spending by Category - Horizontal Ranked Bar Chart
    |--------------------------------------------------------------------------
    */

    function categorySpendingChart() {
        const canvas = document.getElementById('categorySpendingChart');

        if (!canvas || !data.category_spending) {
            return;
        }

        const source = data.category_spending;
        const theme = themeColors();

        // Match every amount to its category and original colour
        // before sorting, so colours cannot get mixed up.
        const categories = (source.labels || []).map((label, index) => {
            const customColor = source.colors?.[index];

            return {
                name: String(label),
                amount: Number(source.amounts?.[index] ?? 0),
                color: /^#[0-9a-f]{6}$/i.test(customColor || '')
                    ? customColor
                    : palette[index % palette.length]
            };
        });

        // Highest spending first.
        categories.sort((a, b) => b.amount - a.amount);

        const labels = categories.map(category => category.name);
        const amounts = categories.map(category => category.amount);
        const colors = categories.map(category => category.color);

        // Make room for all categories.
        // The Blade wrapper controls scrolling.
        const chartHeight = Math.max(
            320,
            categories.length * 42 + 80
        );

        const chartContainer = canvas.parentElement;

        if (chartContainer) {
            chartContainer.style.height = chartHeight + 'px';
            chartContainer.style.position = 'relative';
        }

        const options = commonOptions();

        options.indexAxis = 'y';

        options.layout = {
            padding: {
                right: 15,
                left: 5,
                top: 5,
                bottom: 5
            }
        };

        options.plugins.legend.display = false;

        options.plugins.tooltip.callbacks = {
            title(context) {
                return context[0]?.label || '';
            },
            label(context) {
                return 'Spent: ' + money(context.parsed.x);
            }
        };

        options.scales = {
            x: {
                beginAtZero: true,
                ticks: {
                    color: theme.muted,
                    callback(value) {
                        return 'M ' + minorToMajor(value)
                            .toLocaleString('en-LS');
                    }
                },
                grid: {
                    color: theme.border
                },
                border: {
                    display: false
                }
            },
            y: {
                ticks: {
                    color: theme.text,
                    autoSkip: false,
                    font: {
                        size: 12
                    },
                    callback(value) {
                        const label = this.getLabelForValue(value);

                        // Shorten long labels on the axis.
                        // The tooltip still shows the full name.
                        return label.length > 26
                            ? label.slice(0, 25) + '…'
                            : label;
                    }
                },
                grid: {
                    display: false
                },
                border: {
                    display: false
                }
            }
        };

        addChart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Spending',
                        data: amounts,
                        backgroundColor: colors,
                        hoverBackgroundColor: colors,
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: 24,
                        barPercentage: 0.8,
                        categoryPercentage: 0.8
                    }
                ]
            },
            options
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Budget Performance
    |--------------------------------------------------------------------------
    */

    function budgetPerformanceChart() {
        const canvas = document.getElementById('budgetPerformanceChart');

        if (!canvas || !data.budget_performance) {
            return;
        }

        const source = data.budget_performance;
        const theme = themeColors();

        const options = commonOptions();

        options.indexAxis = 'y';

        options.scales.x = {
            beginAtZero: true,
            ticks: {
                color: theme.muted,
                callback(value) {
                    return 'M ' + minorToMajor(value)
                        .toLocaleString('en-LS');
                }
            },
            grid: {
                color: theme.border
            },
            border: {
                display: false
            }
        };

        options.scales.y = {
            ticks: {
                color: theme.muted,
                autoSkip: false,
                callback(value) {
                    return this.getLabelForValue(value);
                }
            },
            grid: {
                display: false
            },
            border: {
                display: false
            }
        };

        addChart(canvas, {
            type: 'bar',
            data: {
                labels: source.labels,
                datasets: [
                    {
                        label: 'Allocated',
                        data: source.allocated,
                        backgroundColor: theme.budget,
                        borderRadius: 5,
                        maxBarThickness: 20
                    },
                    {
                        label: 'Spent',
                        data: source.spent,
                        backgroundColor: theme.expense,
                        borderRadius: 5,
                        maxBarThickness: 20
                    }
                ]
            },
            options
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Dark / Light Theme Support
    |--------------------------------------------------------------------------
    */

    function refreshChartThemes() {
        const theme = themeColors();

        instances.forEach(chart => {
            if (chart.options.plugins?.legend?.labels) {
                chart.options.plugins.legend.labels.color = theme.text;
            }

            if (chart.options.scales) {
                Object.values(chart.options.scales).forEach(scale => {
                    if (scale.ticks) {
                        scale.ticks.color = theme.muted;
                    }

                    if (scale.grid && scale.grid.display !== false) {
                        scale.grid.color = theme.border;
                    }
                });

                if (
                    chart.canvas.id === 'categorySpendingChart' &&
                    chart.options.scales.y?.ticks
                ) {
                    chart.options.scales.y.ticks.color = theme.text;
                }
            }

            chart.update('none');
        });
    }

    incomeExpenseChart();
    categorySpendingChart();
    budgetPerformanceChart();

    const observer = new MutationObserver(mutations => {
        if (mutations.some(m =>
            m.attributeName === 'data-bs-theme' ||
            m.attributeName === 'data-theme'
        )) {
            refreshChartThemes();
        }
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme', 'data-theme']
    });
})();
