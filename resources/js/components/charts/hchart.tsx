import Highcharts from 'highcharts';
import HighchartsReactDefault from 'highcharts-react-official';
import { useAppearance } from '@/hooks/use-appearance';

// Interop CJS/ESM: según el bundler, el import default puede llegar como el
// namespace del módulo ({ default: Componente }) en vez del componente.
const HighchartsReact =
    (
        HighchartsReactDefault as unknown as {
            default?: typeof HighchartsReactDefault;
        }
    ).default ?? HighchartsReactDefault;

/** Paleta categórica validada (CVD-safe), por tema. */
const COLORES_CLARO = [
    '#2a78d6',
    '#1baf7a',
    '#eda100',
    '#008300',
    '#4a3aa7',
    '#e34948',
    '#e87ba4',
    '#eb6834',
];
const COLORES_OSCURO = [
    '#3987e5',
    '#199e70',
    '#c98500',
    '#008300',
    '#9085e9',
    '#e66767',
    '#d55181',
    '#d95926',
];

/** Opciones base compartidas (sin colores de tema; eso lo pone HChart). */
export function baseOptions(extra: Highcharts.Options): Highcharts.Options {
    return Highcharts.merge<Highcharts.Options>(
        {
            credits: { enabled: false },
            title: { text: undefined },
            chart: {
                backgroundColor: 'transparent',
                style: {
                    fontFamily:
                        'system-ui, -apple-system, "Segoe UI", sans-serif',
                },
            },
        },
        extra,
    );
}

/** Chrome dependiente del tema (colores de serie, ejes, etiquetas, tooltip). */
function temaOptions(oscuro: boolean): Highcharts.Options {
    const texto = oscuro ? '#c3c2b7' : '#52514e';
    const tinta = oscuro ? '#ffffff' : '#0b0b0b';
    const eje = oscuro ? '#383835' : '#c3c2b7';
    const grid = oscuro ? '#2c2c2a' : '#e1e0d9';
    const superficie = oscuro ? '#1a1a19' : '#fcfcfb';

    return {
        colors: oscuro ? COLORES_OSCURO : COLORES_CLARO,
        xAxis: {
            labels: { style: { color: texto } },
            lineColor: eje,
            tickColor: eje,
        },
        yAxis: {
            labels: { style: { color: texto } },
            gridLineColor: grid,
            title: { style: { color: texto } },
        },
        legend: { itemStyle: { color: texto } },
        tooltip: {
            backgroundColor: superficie,
            borderColor: grid,
            style: { color: tinta },
        },
        plotOptions: {
            series: { dataLabels: { style: { color: tinta } } },
        },
    };
}

export function HChart({ options }: { options: Highcharts.Options }) {
    const { resolvedAppearance } = useAppearance();
    const oscuro = resolvedAppearance === 'dark';
    const merged = Highcharts.merge(temaOptions(oscuro), options);

    return (
        <HighchartsReact
            key={resolvedAppearance}
            highcharts={Highcharts}
            options={merged}
        />
    );
}
