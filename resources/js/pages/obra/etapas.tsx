export type EtapaOpcion = { clave: string; label: string };

/**
 * Colores del legacy, uno por etapa (`index.php` de obrapublica): azul, verde,
 * violeta, teal, cian, naranja y café. Gris = etapa que aún no se cierra.
 */
export const TONO: Record<string, string> = {
    ficha: 'bg-blue-500',
    expediente: 'bg-emerald-500',
    autorizacion: 'bg-violet-500',
    adjudicacion: 'bg-teal-500',
    ejecucion: 'bg-cyan-500',
    conclusion: 'bg-orange-500',
    entrega: 'bg-amber-700',
};

/** Estas dos no se habilitan hasta que la autorización fija la modalidad. */
const REQUIEREN_MODALIDAD = ['adjudicacion', 'ejecucion'];

/**
 * Riel de las siete etapas del expediente. Se lee de un vistazo hasta dónde
 * llegó la obra: en color lo cerrado, en gris lo que falta.
 */
export function RielEtapas({
    etapas,
    estado,
    conModalidad,
    onAbrir,
}: {
    etapas: EtapaOpcion[];
    estado: Record<string, boolean>;
    conModalidad: boolean;
    onAbrir: (clave: string) => void;
}) {
    return (
        <div className="flex gap-1">
            {etapas.map(({ clave, label }) => {
                const cerrada = estado[clave] === true;
                const bloqueada =
                    !conModalidad && REQUIEREN_MODALIDAD.includes(clave);

                return (
                    <button
                        key={clave}
                        type="button"
                        disabled={bloqueada}
                        aria-label={label}
                        title={
                            bloqueada
                                ? `${label} · no disponible hasta que se defina la modalidad de ejecución`
                                : `${label} · ${cerrada ? 'cerrada' : 'pendiente'}`
                        }
                        onClick={() => onAbrir(clave)}
                        className="group/etapa py-2"
                    >
                        <span
                            className={`block h-1.5 w-6 rounded-full transition-transform group-hover/etapa:scale-y-150 ${
                                bloqueada
                                    ? 'bg-muted-foreground/20'
                                    : cerrada
                                      ? TONO[clave]
                                      : 'bg-muted-foreground/30'
                            }`}
                        />
                    </button>
                );
            })}
        </div>
    );
}
