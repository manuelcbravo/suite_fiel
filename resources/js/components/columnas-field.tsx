import { useState } from 'react';

export type ColumnaOpcion = { clave: string; label: string };

/**
 * Fichas marcadas, sincronizadas con lo que el servidor terminó aplicando.
 *
 * Hace falta porque hay dos casos en que lo aplicado no es lo que se marcó:
 * cuando no se marca ninguna (el servidor pone las de por omisión) y cuando se
 * cambia de directorio (el catálogo entero es otro).
 */
export function useColumnas(
    aplicadas: string[],
): [string[], (columnas: string[]) => void] {
    const firma = aplicadas.join(',');
    const [columnas, setColumnas] = useState<string[]>(aplicadas);
    const [firmaPrevia, setFirmaPrevia] = useState(firma);

    // Ajuste de estado durante el render, el patrón que documenta React para
    // "derivar de props": React vuelve a renderizar de inmediato, sin parpadeo.
    if (firmaPrevia !== firma) {
        setFirmaPrevia(firma);
        setColumnas(aplicadas);
    }

    return [columnas, setColumnas];
}

/**
 * Qué columnas se lleva el reporte: las casillas del legacy, aquí como fichas
 * que se prenden y apagan. El orden de la tabla lo manda el catálogo del
 * servidor, no el orden en que se marcaron.
 */
export function ColumnasField({
    opciones,
    value,
    onChange,
}: {
    opciones: ColumnaOpcion[];
    value: string[];
    onChange: (columnas: string[]) => void;
}) {
    const alternar = (clave: string) =>
        onChange(
            value.includes(clave)
                ? value.filter((c) => c !== clave)
                : [...value, clave],
        );

    return (
        <div>
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p className="text-sm text-muted-foreground">
                    <span className="font-medium text-foreground tabular-nums">
                        {value.length} de {opciones.length}
                    </span>{' '}
                    marcadas. Salen en este orden, en la tabla y en el Excel.
                </p>
                <div className="flex gap-3 text-xs">
                    <button
                        type="button"
                        className="text-muted-foreground underline-offset-4 hover:underline"
                        onClick={() => onChange(opciones.map((o) => o.clave))}
                    >
                        Marcar todas
                    </button>
                    <button
                        type="button"
                        className="text-muted-foreground underline-offset-4 hover:underline"
                        onClick={() => onChange([])}
                    >
                        Quitar todas
                    </button>
                </div>
            </div>

            <div className="flex flex-wrap gap-2">
                {opciones.map(({ clave, label }) => (
                    <label key={clave} className="cursor-pointer text-sm">
                        <input
                            type="checkbox"
                            className="peer sr-only"
                            checked={value.includes(clave)}
                            onChange={() => alternar(clave)}
                        />
                        <span className="inline-flex rounded-full border px-3 py-1 text-xs text-muted-foreground transition-colors peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 hover:bg-accent">
                            {label}
                        </span>
                    </label>
                ))}
            </div>

            {value.length === 0 && (
                <p className="mt-3 text-xs text-muted-foreground">
                    Sin ninguna marcada se usan las columnas de siempre.
                </p>
            )}
        </div>
    );
}
