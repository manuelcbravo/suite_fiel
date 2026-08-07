const MXN = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

/**
 * Formatea un importe en pesos. Varios montos vienen del legacy como texto
 * libre, así que lo que no sea un número se devuelve tal cual en vez de
 * convertirse en "$NaN".
 */
export function pesos(valor: string | number | null | undefined): string {
    if (valor === null || valor === undefined || valor === '') {
        return '';
    }

    const numero = typeof valor === 'number' ? valor : Number(valor);

    return Number.isFinite(numero) ? MXN.format(numero) : String(valor);
}

/** Un porcentaje capturado como número suelto ("14" → "14%"). */
export function porcentaje(valor: string | number | null | undefined): string {
    if (valor === null || valor === undefined || valor === '') {
        return '';
    }

    const texto = String(valor).trim();

    return texto.endsWith('%') ? texto : `${texto}%`;
}
