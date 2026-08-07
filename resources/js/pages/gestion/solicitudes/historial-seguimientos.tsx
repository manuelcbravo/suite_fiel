import type { Seguimiento } from './types';

/**
 * Historial de seguimientos tal como lo pinta el legacy arriba de las pantallas
 * de seguimiento y de atender/resolver: una tabla con las 9 columnas del flujo.
 */
export function HistorialSeguimientos({
    seguimientos,
}: {
    seguimientos: Seguimiento[];
}) {
    if (seguimientos.length === 0) {
        return (
            <p className="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                La solicitud aún no tiene seguimientos.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-md border">
            <table className="w-full min-w-[900px] border-collapse text-xs">
                <thead>
                    <tr className="bg-muted/60 text-left">
                        {[
                            'Fecha',
                            'Quién',
                            'Estatus',
                            'Dependencia - Dirección',
                            'Responsable',
                            'Instrucción',
                            'Comentario',
                            'Estatus respuesta',
                            'Respuesta',
                        ].map((titulo) => (
                            <th
                                key={titulo}
                                className="border-b px-2 py-2 font-medium whitespace-nowrap"
                            >
                                {titulo}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {seguimientos.map((sg) => (
                        <tr key={sg.id} className="align-top">
                            <td className="border-b px-2 py-2 whitespace-nowrap">
                                {sg.fecha ?? '—'}
                            </td>
                            <td className="border-b px-2 py-2">
                                {sg.autor ?? '—'}
                            </td>
                            <td className="border-b px-2 py-2 whitespace-nowrap">
                                {sg.estatus_label}
                            </td>
                            <td className="border-b px-2 py-2">
                                {[sg.dependencia, sg.area]
                                    .filter(Boolean)
                                    .join(' - ') || '—'}
                            </td>
                            <td className="border-b px-2 py-2">
                                {sg.responsable ?? '—'}
                            </td>
                            <td className="border-b px-2 py-2">
                                {sg.instruccion ?? '—'}
                            </td>
                            <td className="border-b px-2 py-2">
                                {sg.comentario ?? '—'}
                            </td>
                            <td className="border-b px-2 py-2 whitespace-nowrap">
                                {sg.estatus_resp_label ?? '—'}
                            </td>
                            <td className="border-b px-2 py-2">
                                {sg.respuesta ?? '—'}
                                {sg.fecha_respuesta && (
                                    <span className="mt-1 block text-muted-foreground">
                                        {sg.fecha_respuesta}
                                    </span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
