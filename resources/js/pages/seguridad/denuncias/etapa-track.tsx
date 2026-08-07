/**
 * La denuncia recorre tres pasos en orden: recepción → toma de conocimiento →
 * término. Como la secuencia es real y siempre la misma, se dibuja como un
 * riel de tres segmentos lleno hasta donde llegó el caso: bajando la columna
 * se distingue lo que sigue atorado en recepción de lo que ya se cerró.
 */
const PASOS = ['Recepción', 'Toma de conocimiento', 'Término'];

export function EtapaTrack({
    conAtencion,
    conTermino,
}: {
    conAtencion: boolean;
    conTermino: boolean;
}) {
    const avance = conTermino ? 3 : conAtencion ? 2 : 1;
    const cerrada = conTermino;
    const etiqueta = cerrada
        ? 'Concluida'
        : conAtencion
          ? 'En atención'
          : 'Recibida';

    return (
        <div
            className="flex items-center gap-2"
            title={`${etiqueta} — paso ${avance} de 3: ${PASOS[avance - 1]}`}
        >
            <span className="flex gap-0.5" aria-hidden>
                {PASOS.map((paso, i) => (
                    <span
                        key={paso}
                        className={`h-1.5 w-4 rounded-full ${
                            i < avance
                                ? cerrada
                                    ? 'bg-emerald-500 dark:bg-emerald-400'
                                    : 'bg-primary'
                                : 'bg-muted'
                        }`}
                    />
                ))}
            </span>
            <span
                className={`text-xs whitespace-nowrap ${
                    cerrada
                        ? 'text-emerald-700 dark:text-emerald-400'
                        : 'text-muted-foreground'
                }`}
            >
                {etiqueta}
            </span>
        </div>
    );
}
