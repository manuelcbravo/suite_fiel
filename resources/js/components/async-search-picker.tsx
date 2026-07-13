import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Resultado = { id: number; nombre: string };

type AsyncSearchPickerProps = {
    label: string;
    /** URL que recibe ?busqueda= y devuelve [{id, nombre}]. */
    searchUrl: string;
    value: number | null;
    valueLabel?: string | null;
    onChange: (id: number | null, label: string | null) => void;
    error?: string;
    placeholder?: string;
};

/** Selector con búsqueda remota genérica (beneficiarios, eventos, etc.). */
export function AsyncSearchPicker({
    label,
    searchUrl,
    value,
    valueLabel,
    onChange,
    error,
    placeholder = 'Buscar (mín. 2 letras)...',
}: AsyncSearchPickerProps) {
    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState<Resultado[]>([]);
    const [abierto, setAbierto] = useState(false);
    const [labelLocal, setLabelLocal] = useState<string | null>(null);
    const contenedor = useRef<HTMLDivElement>(null);

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

            const sep = searchUrl.includes('?') ? '&' : '?';
            const respuesta = await fetch(
                `${searchUrl}${sep}busqueda=${encodeURIComponent(term)}`,
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
    }, [busqueda, abierto, searchUrl]);

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
                        onClick={() => {
                            onChange(null, null);
                            setLabelLocal(null);
                            setBusqueda('');
                        }}
                    >
                        <X className="size-4" />
                    </Button>
                </div>
            ) : (
                <div className="relative" ref={contenedor}>
                    <Input
                        value={busqueda}
                        placeholder={placeholder}
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
