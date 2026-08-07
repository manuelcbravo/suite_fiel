import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AsyncSearchPicker } from '@/components/async-search-picker';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { SelectField } from '@/components/select-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Documento = {
    clave: string;
    label: string;
    presente: boolean | null;
    observacion: string;
};
type Campo = {
    campo: string;
    label: string;
    tipo: 'texto' | 'fecha' | 'area' | 'opciones' | 'multiple' | 'empresa';
    catalogo: string | null;
};
type Grupo = { titulo: string; campos: Campo[] };
type Etapa = {
    etapa: string;
    titulo: string;
    completo: boolean;
    catalogos: Record<string, Record<string, string>>;
    grupos: Grupo[];
    datos: Record<string, string>;
    empresa_nombre: string | null;
    documentos: Documento[];
};

/** Rótulo de sección dentro del diálogo. */
function Rotulo({ children }: { children: string }) {
    return (
        <h3 className="flex items-center gap-3 pt-2 text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
            <span className="whitespace-nowrap">{children}</span>
            <span className="h-px flex-1 bg-border" />
        </h3>
    );
}

/**
 * Acuse de tres estados, como el legacy: sí, no, o todavía sin capturar.
 * Volver a picarle al botón activo regresa a "sin capturar".
 */
function Acuse({
    valor,
    onChange,
}: {
    valor: boolean | null;
    onChange: (v: boolean | null) => void;
}) {
    const boton = (v: boolean, texto: string) => (
        <button
            type="button"
            aria-pressed={valor === v}
            className={`px-2.5 py-1 text-xs transition-colors ${
                valor === v
                    ? v
                        ? 'bg-emerald-600 text-white'
                        : 'bg-destructive text-white'
                    : 'text-muted-foreground hover:bg-accent'
            }`}
            onClick={() => onChange(valor === v ? null : v)}
        >
            {texto}
        </button>
    );

    return (
        <div className="inline-flex shrink-0 overflow-hidden rounded-md border">
            {boton(true, 'Sí')}
            {boton(false, 'No')}
        </div>
    );
}

/** Dibuja un campo de la etapa según su tipo. */
function CampoEtapa({
    campo,
    valor,
    catalogos,
    empresaNombre,
    onChange,
}: {
    campo: Campo;
    valor: string;
    catalogos: Record<string, Record<string, string>>;
    empresaNombre: string | null;
    onChange: (v: string) => void;
}) {
    const opciones = campo.catalogo
        ? Object.entries(catalogos[campo.catalogo] ?? {}).map(
              ([id, nombre]) => ({ id: Number(id), nombre }),
          )
        : [];

    if (campo.tipo === 'opciones') {
        return (
            <SelectField
                label={campo.label}
                value={valor === '' ? null : Number(valor)}
                options={opciones}
                onChange={(v) => onChange(v === null ? '' : String(v))}
            />
        );
    }

    if (campo.tipo === 'empresa') {
        return (
            <AsyncSearchPicker
                label={campo.label}
                searchUrl="/obra/proveedores"
                value={valor === '' ? null : Number(valor)}
                valueLabel={empresaNombre}
                onChange={(id) => onChange(id === null ? '' : String(id))}
                placeholder="Buscar proveedor por nombre o RFC..."
            />
        );
    }

    if (campo.tipo === 'multiple') {
        // Lista de ids separada por coma, tal como la guarda el legacy.
        const marcados = valor === '' ? [] : valor.split(',');
        const alternar = (id: string) =>
            onChange(
                (marcados.includes(id)
                    ? marcados.filter((m) => m !== id)
                    : [...marcados, id]
                )
                    .sort((a, b) => Number(a) - Number(b))
                    .join(','),
            );

        return (
            <Field className="sm:col-span-2">
                <Label>{campo.label}</Label>
                <div className="flex flex-wrap gap-x-5 gap-y-2 rounded-md border p-3">
                    {opciones.map((o) => (
                        <label
                            key={o.id}
                            className="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={marcados.includes(String(o.id))}
                                onCheckedChange={() => alternar(String(o.id))}
                            />
                            {o.nombre}
                        </label>
                    ))}
                </div>
            </Field>
        );
    }

    if (campo.tipo === 'area') {
        return (
            <Field className="sm:col-span-2">
                <Label>{campo.label}</Label>
                <textarea
                    rows={2}
                    className="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                    value={valor}
                    onChange={(e) => onChange(e.target.value)}
                />
            </Field>
        );
    }

    return (
        <Field>
            <Label>{campo.label}</Label>
            <Input
                type={campo.tipo === 'fecha' ? 'date' : 'text'}
                value={valor}
                onChange={(e) => onChange(e.target.value)}
            />
        </Field>
    );
}

export function EtapaDialog({
    obraId,
    etapa,
    onClose,
}: {
    obraId: number | null;
    etapa: string | null;
    onClose: () => void;
}) {
    const [datos, setDatos] = useState<Etapa | null>(null);
    const [guardando, setGuardando] = useState(false);

    useEffect(() => {
        if (obraId === null || etapa === null) {
            return;
        }

        let vigente = true;

        fetch(`/obra/${obraId}/etapas/${etapa}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(r)))
            .then((d) => vigente && setDatos(d))
            .catch(() => toast.error('No se pudo cargar la etapa.'));

        return () => {
            vigente = false;
        };
    }, [obraId, etapa]);

    const cambiarDocumento = (clave: string, patch: Partial<Documento>) =>
        setDatos((previo) =>
            previo === null
                ? previo
                : {
                      ...previo,
                      documentos: previo.documentos.map((d) =>
                          d.clave === clave ? { ...d, ...patch } : d,
                      ),
                  },
        );

    const cambiarDato = (campo: string, valor: string) =>
        setDatos((previo) =>
            previo === null
                ? previo
                : { ...previo, datos: { ...previo.datos, [campo]: valor } },
        );

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (datos === null || obraId === null) {
            return;
        }

        setGuardando(true);
        router.put(`/obra/${obraId}/etapas/${datos.etapa}`, datos, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: () => toast.error('No se pudo guardar la etapa.'),
            onFinish: () => setGuardando(false),
        });
    };

    const capturados =
        datos?.documentos.filter(
            (d) => d.presente !== null || d.observacion !== '',
        ).length ?? 0;

    return (
        <CrudFormDialog
            open={obraId !== null && etapa !== null}
            onOpenChange={(o) => !o && onClose()}
            size="lg"
            title={datos?.titulo ?? 'Etapa'}
            description={
                datos && datos.documentos.length > 0
                    ? `${capturados} de ${datos.documentos.length} papeles con dato.`
                    : 'Datos de la etapa.'
            }
            submitLabel="Guardar etapa"
            processing={guardando || datos === null}
            onSubmit={enviar}
        >
            {/* No se monta hasta tener el dato: un select que nace sin su
                opción descarta el valor y la etapa se guardaría a medias. */}
            {datos === null ? (
                <p className="py-12 text-center text-sm text-muted-foreground">
                    Cargando la etapa…
                </p>
            ) : (
                <>
                    {datos.grupos.map((g) => (
                        <div key={g.titulo} className="space-y-3">
                            <Rotulo>{g.titulo}</Rotulo>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {g.campos.map((c) => (
                                    <CampoEtapa
                                        key={c.campo}
                                        campo={c}
                                        valor={datos.datos[c.campo] ?? ''}
                                        catalogos={datos.catalogos}
                                        empresaNombre={datos.empresa_nombre}
                                        onChange={(v) =>
                                            cambiarDato(c.campo, v)
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    ))}

                    {datos.documentos.length > 0 && (
                        <div className="space-y-3">
                            <Rotulo>Papeles del expediente</Rotulo>
                            <div className="divide-y divide-sidebar-border/70 overflow-hidden rounded-xl border border-sidebar-border/70">
                                {datos.documentos.map((d) => (
                                    <div key={d.clave} className="p-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="min-w-0 flex-1 text-sm">
                                                {d.label}
                                            </p>
                                            <Acuse
                                                valor={d.presente}
                                                onChange={(presente) =>
                                                    cambiarDocumento(d.clave, {
                                                        presente,
                                                    })
                                                }
                                            />
                                        </div>
                                        <Input
                                            value={d.observacion}
                                            placeholder="Observación (opcional)"
                                            className="mt-2 h-8 text-sm"
                                            onChange={(e) =>
                                                cambiarDocumento(d.clave, {
                                                    observacion: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <label className="flex items-center gap-2 pt-2 text-sm">
                        <Checkbox
                            checked={datos.completo}
                            onCheckedChange={(v) =>
                                setDatos({ ...datos, completo: Boolean(v) })
                            }
                        />
                        Dar por cerrada la etapa de {datos.titulo.toLowerCase()}
                    </label>
                </>
            )}
        </CrudFormDialog>
    );
}
