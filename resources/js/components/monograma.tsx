import { useState } from 'react';


/**
 * Casi ningún registro del directorio tiene foto, así que el hueco gris no
 * decía nada y todas las fichas se veían iguales. En su lugar van las
 * iniciales sobre un color derivado del nombre — como la pestaña de color de
 * una carpeta de archivo: siempre el mismo color para el mismo registro, y
 * suficiente para distinguirlos de un vistazo. Si hay foto, la foto manda.
 *
 * Los tonos son apagados a propósito: identifican, no gritan.
 */
const TONOS = [
    'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300',
    'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
    'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
];

/** Mismo nombre, mismo tono, siempre. */
function tono(texto: string): string {
    let suma = 0;

    for (let i = 0; i < texto.length; i++) {
        suma = (suma + texto.charCodeAt(i)) % 4096;
    }

    return TONOS[suma % TONOS.length];
}

/** Dos letras: la inicial de las dos primeras palabras con letra. */
function iniciales(texto: string): string {
    const palabras = texto
        .split(/\s+/)
        .map((p) => p.replace(/[^\p{L}]/gu, ''))
        .filter(Boolean);

    if (palabras.length === 0) {
        return '·';
    }

    if (palabras.length === 1) {
        return palabras[0].slice(0, 2).toUpperCase();
    }

    return (palabras[0][0] + palabras[1][0]).toUpperCase();
}

const TAMANOS = {
    sm: 'size-9 rounded-md text-[11px]',
    md: 'size-14 rounded-lg text-base',
    lg: 'size-20 rounded-xl text-2xl',
};

export function Monograma({
    nombre,
    fotoUrl,
    tamano = 'md',
    className = '',
}: {
    nombre: string;
    /** URL de la foto; `null` cuando el registro no tiene y va el monograma. */
    fotoUrl?: string | null;
    tamano?: keyof typeof TAMANOS;
    className?: string;
}) {
    // `fallo` cubre el caso raro de una foto guardada que no se puede decodificar.
    const [fallo, setFallo] = useState(false);
    const base = `${TAMANOS[tamano]} shrink-0 overflow-hidden ${className}`;

    if (! fotoUrl || fallo) {
        return (
            <span
                aria-hidden
                className={`${base} ${tono(nombre)} grid place-items-center font-semibold tracking-tight`}
            >
                {iniciales(nombre)}
            </span>
        );
    }

    return (
        <span className={`${base} block bg-muted`}>
            <img
                src={fotoUrl}
                alt=""
                loading="lazy"
                className="size-full object-cover"
                onError={() => setFallo(true)}
            />
        </span>
    );
}
