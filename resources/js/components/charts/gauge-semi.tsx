/**
 * Medidor semicircular tipo aguja con bandas rojo/amarillo/verde
 * (0-30-70-100), dibujado en SVG puro (sin dependencias de Highcharts).
 * Replica el gauge del sistema legacy.
 */

const R = 80;
const CX = 100;
const CY = 100;

// valor 0..100 -> punto (x,y) sobre el semicírculo superior.
function punto(valor: number, radio = R): [number, number] {
    const t = Math.max(0, Math.min(100, valor)) / 100;
    const ang = Math.PI * (1 - t); // 0 -> 180°, 100 -> 0°

    return [CX + radio * Math.cos(ang), CY - radio * Math.sin(ang)];
}

function arco(desde: number, hasta: number, radio = R): string {
    const [x0, y0] = punto(desde, radio);
    const [x1, y1] = punto(hasta, radio);

    return `M ${x0} ${y0} A ${radio} ${radio} 0 0 1 ${x1} ${y1}`;
}

export function GaugeSemi({ value }: { value: number }) {
    const [ax, ay] = punto(value, R - 10);

    return (
        <div className="flex flex-col items-center justify-center py-2">
            <svg viewBox="0 0 200 120" className="w-full max-w-[260px]">
                <path
                    d={arco(0, 30)}
                    fill="none"
                    stroke="#d03b3b"
                    strokeWidth={16}
                />
                <path
                    d={arco(30, 70)}
                    fill="none"
                    stroke="#fab219"
                    strokeWidth={16}
                />
                <path
                    d={arco(70, 100)}
                    fill="none"
                    stroke="#0ca30c"
                    strokeWidth={16}
                />
                {/* Aguja */}
                <line
                    x1={CX}
                    y1={CY}
                    x2={ax}
                    y2={ay}
                    stroke="currentColor"
                    strokeWidth={3}
                    strokeLinecap="round"
                />
                <circle cx={CX} cy={CY} r={5} fill="currentColor" />
            </svg>
            <p className="-mt-2 text-2xl font-semibold tabular-nums">
                {Math.round(value)}%
            </p>
        </div>
    );
}
