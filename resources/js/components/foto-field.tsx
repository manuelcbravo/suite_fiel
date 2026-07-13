import { ImageUp, X } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Label } from '@/components/ui/label';

/**
 * Campo de foto: muestra la imagen actual (data URI) y permite subir/reemplazar
 * o quitar. La imagen se entrega como data URI al formulario.
 */
export function FotoField({
    label = 'Foto',
    value,
    onChange,
}: {
    label?: string;
    value: string | null;
    onChange: (dataUri: string | null) => void;
}) {
    const input = useRef<HTMLInputElement>(null);

    const elegir = (archivo: File | undefined) => {
        if (!archivo) {
            return;
        }

        const lector = new FileReader();
        lector.onload = () => onChange(lector.result as string);
        lector.readAsDataURL(archivo);
    };

    return (
        <Field>
            <Label>{label}</Label>
            <div className="flex items-center gap-3">
                <div className="grid size-16 shrink-0 place-items-center overflow-hidden rounded-md border bg-muted">
                    {value ? (
                        <img
                            src={value}
                            alt={label}
                            className="size-full object-cover"
                        />
                    ) : (
                        <ImageUp className="size-5 text-muted-foreground" />
                    )}
                </div>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => input.current?.click()}
                    >
                        <ImageUp className="mr-2 size-4" />
                        {value ? 'Reemplazar' : 'Subir'}
                    </Button>
                    {value && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => onChange(null)}
                        >
                            <X className="mr-1 size-4" /> Quitar
                        </Button>
                    )}
                </div>
                <input
                    ref={input}
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={(e) => elegir(e.target.files?.[0])}
                />
            </div>
        </Field>
    );
}
