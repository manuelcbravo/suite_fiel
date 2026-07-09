import type { ComponentProps } from 'react';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FormInputFieldProps = {
    id: string;
    label: string;
    value: string;
    error?: string;
} & Omit<ComponentProps<typeof Input>, 'id' | 'value'>;

export function FormInputField({
    id,
    label,
    value,
    error,
    ...inputProps
}: FormInputFieldProps) {
    return (
        <Field>
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                value={value}
                aria-invalid={Boolean(error)}
                {...inputProps}
            />
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
