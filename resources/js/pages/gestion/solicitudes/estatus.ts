/**
 * El estatus es lo que gobierna esta pantalla: decide qué acciones existen y
 * qué sigue en el trámite. El color se deriva de la etapa del flujo, no del
 * gusto, y se reutiliza en el sello y en el riel de la fila para que el
 * expediente se lea de un vistazo.
 *
 *   0 Capturada          inerte, todavía no se mueve      pizarra
 *   1 Turnada            en tránsito, ya tiene dueño      azul
 *   2 No aprobada        cerrada en negativo              rojo
 *   3 Para resolver      esperando resolución             ámbar
 *   4 Respuesta de área  regresó con respuesta            violeta
 *   5 Atendida           cerrada bien                     esmeralda
 *   6 Atención rápida    cerrada en el acto               verde azulado
 *
 * Las clases van completas y literales: Tailwind no compila nombres armados
 * en tiempo de ejecución.
 */
type Sello = { sello: string; riel: string };

const NEUTRO: Sello = {
    sello: 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300',
    riel: 'border-slate-300 dark:border-slate-700',
};

export const ESTATUS_SELLO: Record<number, Sello> = {
    0: NEUTRO,
    1: {
        sello: 'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
        riel: 'border-blue-400 dark:border-blue-700',
    },
    2: {
        sello: 'border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/60 dark:text-red-300',
        riel: 'border-red-400 dark:border-red-800',
    },
    3: {
        sello: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300',
        riel: 'border-amber-400 dark:border-amber-700',
    },
    4: {
        sello: 'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950/60 dark:text-violet-300',
        riel: 'border-violet-400 dark:border-violet-700',
    },
    5: {
        sello: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300',
        riel: 'border-emerald-500 dark:border-emerald-700',
    },
    6: {
        sello: 'border-teal-300 bg-teal-50 text-teal-800 dark:border-teal-900 dark:bg-teal-950/60 dark:text-teal-300',
        riel: 'border-teal-500 dark:border-teal-700',
    },
};

export const selloDe = (status: number): Sello =>
    ESTATUS_SELLO[status] ?? NEUTRO;
