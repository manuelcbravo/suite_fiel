import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Resultado = { id: number; nombre: string };

type BeneficiarioPickerProps = {
    label: string;
    value: number | null;
    valueLabel?: string | null;
    onChange: (id: number | null, label: string | null) => void;
    error?: string;
};

/** Selector con búsqueda remota de beneficiarios (para representantes, etc.). */
export function BeneficiarioPicker({
    label,
    value,
    valueLabel,
    onChange,
    error,
}: BeneficiarioPickerProps) {
    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState<Resultado[]>([]);
    const [abierto, setAbierto] = useState(false);
    // Etiqueta elegida en esta sesión; si es null, cae al prop valueLabel.
    const [labelLocal, setLabelLocal] = useState<string | null>(null);
    const contenedor = useRef<HTMLDivElement>(null);

    // Búsqueda con debounce.
    useEffect(() => {
        let activo = true;
        const term = busqueda.trim();
        const timer = setTimeout(async () => {
            if (!abierto || term.length < 2) {
                if (activo) {
                    setResultados([]);
                }

                return;
            }

            const respuesta = await fetch(
                `/directorio/beneficiarios/buscar?busqueda=${encodeURIComponent(term)}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );

            if (activo && respuesta.ok) {
                setResultados((await respuesta.json()) as Resultado[]);
            }
        }, 300);

        return () => {
            activo = false;
            clearTimeout(timer);
        };
    }, [busqueda, abierto]);

    // Cierra el desplegable al hacer clic fuera.
    useEffect(() => {
        const alClic = (e: MouseEvent) => {
            if (
                contenedor.current &&
                !contenedor.current.contains(e.target as Node)
            ) {
                setAbierto(false);
            }
        };
        document.addEventListener('mousedown', alClic);

        return () => document.removeEventListener('mousedown', alClic);
    }, []);

    const seleccionar = (r: Resultado) => {
        onChange(r.id, r.nombre);
        setLabelLocal(r.nombre);
        setBusqueda('');
        setResultados([]);
        setAbierto(false);
    };

    const limpiar = () => {
        onChange(null, null);
        setLabelLocal(null);
        setBusqueda('');
    };

    return (
        <Field>
            <Label>{label}</Label>
            {value ? (
                <div className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                    <span className="truncate">
                        {labelLocal ?? valueLabel ?? `#${value}`}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-6"
                        onClick={limpiar}
                    >
                        <X className="size-4" />
                    </Button>
                </div>
            ) : (
                <div className="relative" ref={contenedor}>
                    <Input
                        value={busqueda}
                        placeholder="Buscar beneficiario (mín. 2 letras)..."
                        aria-invalid={Boolean(error)}
                        onFocus={() => setAbierto(true)}
                        onChange={(e) => {
                            setBusqueda(e.target.value);
                            setAbierto(true);
                        }}
                    />
                    {abierto && resultados.length > 0 && (
                        <ul className="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover shadow-md">
                            {resultados.map((r) => (
                                <li key={r.id}>
                                    <button
                                        type="button"
                                        className="w-full px-3 py-2 text-left text-sm hover:bg-accent"
                                        onClick={() => seleccionar(r)}
                                    >
                                        {r.nombre}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
