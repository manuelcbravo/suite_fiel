/** Bloque con título dentro de un formulario largo del directorio. */
export function Seccion({
    titulo,
    children,
}: {
    titulo: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-3 border-t pt-4 first:border-t-0 first:pt-0">
            <h3 className="text-sm font-semibold text-muted-foreground">
                {titulo}
            </h3>
            {children}
        </div>
    );
}
