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
            value.includes(id)
                ? value.filter((v) => v !== id)
                : [...value, id],
        );
    };

    return (
        <Field>
            <p className="text-sm font-medium">{label}</p>
            <div className="grid max-h-40 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                {options.map((opcion) => (
                    <label
                        key={opcion.id}
                        className="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            checked={value.includes(opcion.id)}
                            onCheckedChange={() => alternar(opcion.id)}
                        />
                        {opcion.nombre}
                    </label>
                ))}
            </div>
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
