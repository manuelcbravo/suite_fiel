/**
 * Ojo con los valores: en el módulo de detenidos el legacy guarda
 * **2 = HOMBRE y 1 = MUJER** (`dtnd_add.php`), al revés que en el Directorio,
 * donde 1 es masculino. Los datos migrados usan esa convención, así que las
 * etiquetas se derivan de aquí y no se repiten en cada pantalla.
 */
export const SEXOS = [
    { id: 2, nombre: 'Hombre' },
    { id: 1, nombre: 'Mujer' },
];

export function etiquetaSexo(sexo: unknown): string | null {
    return SEXOS.find((s) => s.id === Number(sexo))?.nombre ?? null;
}
