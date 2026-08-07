import { Field, FieldError } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const SIN = '__none__';

type Opcion = { id: number; nombre: string };

type SelectFieldProps = {
    /** Vacío para no dibujar etiqueta (cuando ya la rotula quien lo usa). */
    label: string;
    value: number | null;
    options: Opcion[];
    onChange: (value: number | null) => void;
    error?: string;
    placeholder?: string;
    /** Texto de la opción sin valor; en filtros conviene "Todas"/"Todos". */
    emptyLabel?: string;
    disabled?: boolean;
};

/** Select de catálogo (id/nombre) con opción "ninguno" y valor numérico. */
export function SelectField({
    label,
    value,
    options,
    onChange,
    error,
    placeholder = 'Selecciona...',
    emptyLabel = '— Ninguno —',
    disabled,
}: SelectFieldProps) {
    // El valor actual siempre tiene su opción, aunque la lista todavía no
    // cargue o dependa de otro select: si falta, el componente descarta el
    // valor y lo reporta como cadena vacía, que `Number('')` volvía 0.
    const visibles =
        value && !options.some((o) => o.id === value)
            ? [{ id: value, nombre: '…' }, ...options]
            : options;

    return (
        <Field>
            {label !== '' && <Label>{label}</Label>}
            <Select
                value={value ? String(value) : SIN}
                onValueChange={(v) =>
                    onChange(v === SIN || v === '' ? null : Number(v))
                }
                disabled={disabled}
            >
                <SelectTrigger
                    className="w-full"
                    aria-invalid={Boolean(error)}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={SIN}>{emptyLabel}</SelectItem>
                    {visibles.map((opcion) => (
                        <SelectItem key={opcion.id} value={String(opcion.id)}>
                            {opcion.nombre}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
