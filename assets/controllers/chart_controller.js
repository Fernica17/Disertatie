import { Controller } from '@hotwired/stimulus';
import ApexCharts from 'apexcharts';

export default class extends Controller {
    static values = {
        type: String,
        options: Object,
    }

    connect() {
        const baseOptions = this.optionsValue;

        const themeDefaults = {
            chart: {
                ...baseOptions.chart,
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
                toolbar: { show: false },
                background: 'transparent',
            },
            colors: baseOptions.colors || ['#4361ee', '#2ec4b6', '#ff6b35', '#e63946', '#a855f7', '#06b6d4'],
            grid: {
                borderColor: '#e9ecef',
                strokeDashArray: 3,
            },
            tooltip: {
                theme: 'light',
            },
            ...(baseOptions.chart?.type === 'donut' || baseOptions.chart?.type === 'pie' ? {
                legend: {
                    position: 'bottom',
                    fontSize: '13px',
                    ...baseOptions.legend,
                },
                dataLabels: {
                    enabled: true,
                    style: { fontSize: '13px', fontWeight: 500 },
                    ...baseOptions.dataLabels,
                },
                stroke: { width: 2, colors: ['#fff'] },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '60%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total',
                                    fontSize: '14px',
                                    fontWeight: 600,
                                },
                            },
                        },
                    },
                    ...baseOptions.plotOptions,
                },
            } : {}),
            ...(baseOptions.chart?.type === 'bar' ? {
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        columnWidth: '55%',
                    },
                    ...baseOptions.plotOptions,
                },
                dataLabels: { enabled: false },
                xaxis: {
                    labels: { style: { fontSize: '12px', colors: '#6c757d' } },
                    ...baseOptions.xaxis,
                },
                yaxis: {
                    labels: { style: { fontSize: '12px', colors: '#6c757d' } },
                    ...baseOptions.yaxis,
                },
            } : {}),
        };

        const mergedOptions = this.deepMerge(themeDefaults, baseOptions);
        this.chart = new ApexCharts(this.element, mergedOptions);
        this.chart.render();
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }

    deepMerge(target, source) {
        const result = { ...target };
        for (const key of Object.keys(source)) {
            if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                result[key] = this.deepMerge(result[key] || {}, source[key]);
            } else {
                result[key] = source[key];
            }
        }
        return result;
    }
}
