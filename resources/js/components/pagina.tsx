import type { ReactNode } from 'react';

/**
 * Encabezado de pantalla: el nombre a la izquierda y la cifra que resume la
 * pantalla a la derecha, para que se entienda de qué universo se habla antes
 * de leer una sola gráfica. En las pantallas de captura, `acciones` lleva el
 * botón que abre el alta.
 */
export function Encabezado({
    titulo,
    resumen,
    acciones,
}: {
    titulo: string;
    resumen?: ReactNode;
    acciones?: ReactNode;
}) {
    return (
        <div
            className={`flex flex-wrap justify-between gap-x-6 gap-y-1 ${acciones ? 'items-center' : 'items-baseline'}`}
        >
            <h1 className="text-xl font-semibold tracking-tight">{titulo}</h1>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                {resumen && (
                    <p className="text-sm text-muted-foreground">{resumen}</p>
                )}
                {acciones}
            </div>
        </div>
    );
}

/**
 * Separador de sección: rótulo corto y una línea que ocupa el resto del ancho.
 * Marca dónde cambia el tema del tablero sin robarle peso a los datos.
 */
export function Seccion({ children }: { children: ReactNode }) {
    return (
        <h2 className="flex items-center gap-3 pt-2 text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
            <span className="whitespace-nowrap">{children}</span>
            <span className="h-px flex-1 bg-border" />
        </h2>
    );
}
