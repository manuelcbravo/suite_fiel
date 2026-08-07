/**
 * Casi todas las notas son asientos de correspondencia y abren igual: el tipo
 * de documento y, si lo trae, su folio ("OFICIO 03016 REFERENTE A…"). Esta
 * lectura los separa para poder ponerlos en la columna de margen, como en un
 * libro de gobierno, y que el asiento se encuentre hojeando.
 *
 * Es deliberadamente estricta: si el texto no abre con un tipo conocido, o si
 * lo que sigue no tiene forma de folio, se devuelve el texto intacto. Vale más
 * un asiento sin margen que un margen inventado.
 */
export type Asiento = {
    tipo: string | null;
    folio: string | null;
    cuerpo: string;
};

/** Los tipos que se ven en el libro. Se prueban del más largo al más corto. */
const TIPOS = [
    'NOTAS DEL EVENTO',
    'TARJETA INFORMATIVA',
    'JUICIO DE AMPARO',
    'MEMORANDUM',
    'MEMORÁNDUM',
    'INVITACION',
    'INVITACIÓN',
    'PENDIENTE',
    'CIRCULAR',
    'LLAMADA',
    'INFORME',
    'REUNION',
    'REUNIÓN',
    'OFICIO',
    'ACTA',
].sort((a, b) => b.length - a.length);

/** "OFICIO NUMERO 516/2017" — la palabra que anuncia el folio sobra. */
const ANUNCIO = /^(?:NUMERO|NÚMERO|NUMS?\.?|NÚMS?\.?|NO\.?|N°|#)\s+/;

/** Un folio trae al menos un dígito y sólo signos de expediente. */
const FOLIO = /^(?=[^\s]*\d)[A-ZÁÉÍÓÚÑ0-9][A-ZÁÉÍÓÚÑ0-9/.-]{1,39}$/;

export function leerAsiento(texto: string | null): Asiento {
    // Los dobles espacios del capturista no son parte del asiento.
    const limpio = (texto ?? '').replace(/[^\S\n]{2,}/g, ' ').trim();

    if (limpio === '') {
        return { tipo: null, folio: null, cuerpo: '' };
    }

    const tipo = TIPOS.find(
        (t) =>
            limpio.startsWith(t) &&
            (limpio.length === t.length ||
                /^[\s,;:]/.test(limpio.slice(t.length))),
    );

    if (!tipo) {
        return { tipo: null, folio: null, cuerpo: limpio };
    }

    let resto = limpio.slice(tipo.length).replace(/^[\s,;:]+/, '');
    resto = resto.replace(ANUNCIO, '');

    const primera = resto.split(/\s+/)[0] ?? '';
    const candidato = primera.replace(/[.,;:]+$/, '');
    const folio = FOLIO.test(candidato) ? candidato : null;

    if (folio) {
        // El separador que sostenía al folio queda colgando al moverlo.
        resto = resto.slice(primera.length).replace(/^[\s,;:.–—-]+/, '');
    }

    return { tipo, folio, cuerpo: resto };
}
