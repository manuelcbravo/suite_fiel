import type Highcharts from 'highcharts';
import { baseOptions } from '@/components/charts/hchart';

type Par = [string, number];

const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});

/** Dona (pie) por categoría. */
export function pieDonut(data: Par[]): Highcharts.Options {
    return baseOptions({
        chart: { type: 'pie', height: 260 },
        tooltip: { pointFormat: '<b>{point.y}</b> ({point.percentage:.1f}%)' },
        plotOptions: {
            pie: {
                innerSize: '60%',
                borderWidth: 2,
                dataLabels: {
                    enabled: true,
                    format: '{point.name}: {point.y}',
                    style: { fontWeight: 'normal', textOutline: 'none' },
                },
            },
        },
        series: [{ type: 'pie', name: 'Total', data }],
    });
}

/** Columnas verticales de una serie, con valor anotado. */
export function columnChart(data: Par[], money_ = false): Highcharts.Options {
    const dataLabels: Highcharts.PlotColumnDataLabelsOptions = {
        enabled: true,
        style: { textOutline: 'none' },
    };

    // Solo se agrega `formatter` cuando aplica; asignar `undefined` rompe
    // Highcharts (intenta invocar el formatter inexistente).
    if (money_) {
        dataLabels.formatter = function () {
            return money.format(this.y ?? 0);
        };
    }

    return baseOptions({
        chart: { type: 'column', height: 300 },
        xAxis: { type: 'category' },
        yAxis: { min: 0 },
        tooltip: money_
            ? {
                  pointFormatter() {
                      return `<b>${money.format(this.y ?? 0)}</b>`;
                  },
              }
            : { pointFormat: '<b>{point.y}</b>' },
        plotOptions: {
            column: { borderRadius: 3, colorByPoint: true, dataLabels },
        },
        series: [{ type: 'column', name: 'Total', data }],
    });
}

/** Barras agrupadas de 2 series (Personal / Comunitaria). */
export function groupedBar(
    categories: string[],
    personal: number[],
    comunitaria: number[],
    money_ = false,
): Highcharts.Options {
    return baseOptions({
        chart: { type: 'column', height: 300 },
        xAxis: { categories },
        yAxis: { min: 0 },
        tooltip: money_
            ? {
                  pointFormatter() {
                      return `${this.series.name}: <b>${money.format(this.y ?? 0)}</b><br/>`;
                  },
                  shared: true,
              }
            : { shared: true },
        legend: { enabled: true },
        plotOptions: { column: { borderRadius: 3 } },
        series: [
            { type: 'column', name: 'Personal', data: personal },
            { type: 'column', name: 'Comunitaria', data: comunitaria },
        ],
    });
}

