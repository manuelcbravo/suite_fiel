import type Highcharts from 'highcharts';
import { baseOptions } from '@/components/charts/hchart';

type Par = [string, number];

const nf = new Intl.NumberFormat('es-MX');
const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});

/** Dona (pie) por categoría. */
export function pieDonut(data: Par[]): Highcharts.Options {
    const total = data.reduce((acc, [, v]) => acc + v, 0);

    return baseOptions({
        chart: { type: 'pie', height: 300 },
        // El total va en el hueco de la dona: es el dato que se busca primero y
        // así no hay que sumar las rebanadas de cabeza.
        title: {
            useHTML: true,
            text: `<div style="text-align:center;line-height:1.2">
                <div class="text-xl font-semibold tabular-nums text-foreground">${nf.format(total)}</div>
                <div class="text-[11px] text-muted-foreground">Total</div>
            </div>`,
            align: 'center',
            verticalAlign: 'middle',
            y: 6,
        },
        tooltip: {
            pointFormatter() {
                return `<b>${nf.format(this.y ?? 0)}</b> (${(this.percentage ?? 0).toFixed(1)}%)`;
            },
        },
        plotOptions: {
            pie: {
                innerSize: '60%',
                borderWidth: 2,
                dataLabels: {
                    enabled: true,
                    // Etiqueta compacta y de ancho fijo: los nombres largos de
                    // las dependencias se parten en varias líneas en vez de
                    // estirar la línea guía por medio panel.
                    distance: 12,
                    connectorPadding: 3,
                    crookDistance: '60%',
                    formatter() {
                        return `${this.key}<br/><b>${nf.format(this.y ?? 0)}</b>`;
                    },
                    style: {
                        fontSize: '11px',
                        fontWeight: 'normal',
                        textOutline: 'none',
                        width: 130,
                    },
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
        style: { fontSize: '11px', textOutline: 'none' },
        formatter() {
            return money_ ? money.format(this.y ?? 0) : nf.format(this.y ?? 0);
        },
    };

    return baseOptions({
        chart: { type: 'column', height: 320 },
        xAxis: {
            type: 'category',
            lineWidth: 0,
            tickLength: 0,
            // Se giran solo lo necesario: horizontales cuando caben.
            labels: {
                autoRotation: [0, -20, -45],
                style: { fontSize: '11px' },
            },
        },
        // Sin título de eje: Highcharts pone "Values" en inglés por omisión y
        // la unidad ya está en el título del panel.
        yAxis: { min: 0, title: { text: '' } },
        tooltip: {
            pointFormatter() {
                return `<b>${money_ ? money.format(this.y ?? 0) : nf.format(this.y ?? 0)}</b>`;
            },
        },
        // Una sola serie no necesita leyenda: solo repetía "Total".
        legend: { enabled: false },
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
        xAxis: { categories, lineWidth: 0, tickLength: 0 },
        yAxis: { min: 0, title: { text: '' } },
        tooltip: {
            pointFormatter() {
                return `${this.series.name}: <b>${money_ ? money.format(this.y ?? 0) : nf.format(this.y ?? 0)}</b><br/>`;
            },
            shared: true,
        },
        legend: { enabled: true, align: 'left', margin: 20 },
        plotOptions: { column: { borderRadius: 3, groupPadding: 0.26 } },
        series: [
            { type: 'column', name: 'Personal', data: personal },
            { type: 'column', name: 'Comunitaria', data: comunitaria },
        ],
    });
}
