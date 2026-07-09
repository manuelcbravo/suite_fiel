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
    label: string;
    value: number | null;
    options: Opcion[];
    onChange: (value: number | null) => void;
    error?: string;
    placeholder?: string;
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
    disabled,
}: SelectFieldProps) {
    return (
        <Field>
            <Label>{label}</Label>
            <Select
                value={value ? String(value) : SIN}
                onValueChange={(v) => onChange(v === SIN ? null : Number(v))}
                disabled={disabled}
            >
                <SelectTrigger
                    className="w-full"
                    aria-invalid={Boolean(error)}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={SIN}>— Ninguno —</SelectItem>
                    {options.map((opcion) => (
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
