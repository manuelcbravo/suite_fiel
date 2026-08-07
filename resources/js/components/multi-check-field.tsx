import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError } from '@/components/ui/field';

type Opcion = { id: number; nombre: string };

type MultiCheckFieldProps = {
    label: string;
    options: Opcion[];
    value: number[];
    onChange: (value: number[]) => void;
    error?: string;
};

/** Grupo de checkboxes para relaciones muchos-a-muchos (rubros, sectores…). */
export function MultiCheckField({
    label,
    options,
    value,
    onChange,
    error,
}: MultiCheckFieldProps) {
    const alternar = (id: number) => {
        onChange(
            value.includes(id) ? value.filter((v) => v !== id) : [...value, id],
        );
    };

    return (
        <Field>
            <p className="flex items-baseline justify-between gap-2 text-sm font-medium">
                {label}
                {value.length > 0 && (
                    <span className="text-xs font-normal text-muted-foreground tabular-nums">
                        {value.length} marcado{value.length === 1 ? '' : 's'}
                    </span>
                )}
            </p>
            {/* Las columnas las decide el ancho disponible, no un breakpoint:
                el mismo componente aparece en un cuarto de pantalla (donde
                "ALTERAR EL ORDEN, ARROJAR LÍQUIDOS U OBJETOS…" necesita todo
                el renglón) y a lo ancho, donde caben tres. */}
            <div className="grid max-h-44 grid-cols-[repeat(auto-fill,minmax(13rem,1fr))] gap-x-4 gap-y-1.5 overflow-x-hidden overflow-y-auto rounded-md border p-3">
                {options.map((opcion) => (
                    <label
                        key={opcion.id}
                        className="flex cursor-pointer items-start gap-2 text-sm"
                    >
                        <Checkbox
                            className="mt-0.5 shrink-0"
                            checked={value.includes(opcion.id)}
                            onCheckedChange={() => alternar(opcion.id)}
                        />
                        <span className="min-w-0 break-words">
                            {opcion.nombre}
                        </span>
                    </label>
                ))}
            </div>
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
