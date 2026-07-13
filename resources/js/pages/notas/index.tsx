import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    Pencil,
    Plus,
    StickyNote,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AsyncSearchPicker } from '@/components/async-search-picker';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/date';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { destroy, store } from '@/routes/notas';

type Pendiente = { id: number; texto: string; realizado: boolean };
type Nota = {
    id: number;
    nota: string | null;
    fecha: string | null;
    evento_id: number | null;
    evento_titulo: string | null;
    pendientes: Pendiente[];
};
type Form = {
    id: number | null;
    nota: string;
    fecha: string;
    evento_id: number | null;
};

const VACIO: Form = { id: null, nota: '', fecha: '', evento_id: null };

export default function NotasIndex({ notas }: { notas: Nota[] }) {
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const [editando, setEditando] = useState<Nota | null>(null);
    const [eliminar, setEliminar] = useState<Nota | null>(null);
    const form = useForm<Form>(VACIO);
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success]);

    const abrirCrear = () => {
        setEditando(null);
        form.clearErrors();
        form.setData(VACIO);
        setFormMode('create');
    };

    const abrirEditar = (n: Nota) => {
        setEditando(n);
        form.clearErrors();
        form.setData({
            id: n.id,
            nota: n.nota ?? '',
            fecha: n.fecha ?? '',
            evento_id: n.evento_id,
        });
        setFormMode('edit');
    };

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setFormMode(null);
                setEditando(null);
                form.reset();
            },
            onError: (errores) =>
                toast.error(
                    resolveFormErrorMessage(errores, 'Revisa los campos.'),
                ),
        });
    };

    return (
        <>
            <Head title="Notas" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center gap-3">
                        <StickyNote className="size-5 text-primary" />
                        <div>
                            <h1 className="text-xl font-semibold">Notas</h1>
                            <p className="text-sm text-muted-foreground">
                                Notas y pendientes.
                            </p>
                        </div>
                    </div>
                    <Button onClick={abrirCrear}>
                        <Plus className="mr-2 size-4" /> Nueva nota
                    </Button>
                </div>

                {notas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No hay notas todavía.
                    </p>
                ) : (
                    <div className="gap-4 space-y-4 sm:columns-2 lg:columns-3">
                        {notas.map((nota) => (
                            <NotaCard
                                key={nota.id}
                                nota={nota}
                                onEditar={() => abrirEditar(nota)}
                                onEliminar={() => setEliminar(nota)}
                            />
                        ))}
                    </div>
                )}
            </div>

            <CrudFormDialog
                open={formMode !== null}
                onOpenChange={(o) => {
                    if (!o) {
                        setFormMode(null);
                        setEditando(null);
                        form.clearErrors();
                    }
                }}
                size="md"
                title={formMode === 'edit' ? 'Editar nota' : 'Nueva nota'}
                description="Captura el contenido de la nota."
                submitLabel={formMode === 'edit' ? 'Guardar cambios' : 'Guardar'}
                processing={form.processing}
                onSubmit={enviar}
            >
                <Field>
                    <Label htmlFor="nota-texto">Nota</Label>
                    <textarea
                        id="nota-texto"
                        className="min-h-28 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        value={form.data.nota}
                        onChange={(e) => form.setData('nota', e.target.value)}
                    />
                </Field>

                <FormInputField
                    id="nota-fecha"
                    type="date"
                    label="Fecha"
                    value={form.data.fecha}
                    error={form.errors.fecha}
                    onChange={(e) => form.setData('fecha', e.target.value)}
                />

                <AsyncSearchPicker
                    label="Vincular a evento (opcional)"
                    searchUrl="/notas/eventos/buscar"
                    value={form.data.evento_id}
                    valueLabel={editando?.evento_titulo}
                    error={form.errors.evento_id}
                    onChange={(id) => form.setData('evento_id', id)}
                    placeholder="Buscar evento por título..."
                />
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar nota"
                entityLabel="la nota"
                itemName={eliminar?.nota?.slice(0, 40) ?? `#${eliminar?.id}`}
                onConfirm={() => {
                    if (!eliminar) {
                        return;
                    }

                    router.delete(destroy.url(eliminar.id), {
                        preserveScroll: true,
                        onSuccess: () => setEliminar(null),
                        onError: () => toast.error('No se pudo eliminar.'),
                    });
                }}
            />
        </>
    );
}

function NotaCard({
    nota,
    onEditar,
    onEliminar,
}: {
    nota: Nota;
    onEditar: () => void;
    onEliminar: () => void;
}) {
    const [nuevo, setNuevo] = useState('');

    const agregar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (nuevo.trim() === '') {
            return;
        }

        router.post(
            `/notas/${nota.id}/pendientes`,
            { texto: nuevo },
            {
                preserveScroll: true,
                onSuccess: () => setNuevo(''),
                onError: () => toast.error('No se pudo agregar el pendiente.'),
            },
        );
    };

    const alternar = (p: Pendiente) =>
        router.put(
            `/notas/pendientes/${p.id}/toggle`,
            {},
            { preserveScroll: true },
        );

    const borrarPendiente = (p: Pendiente) =>
        router.delete(`/notas/pendientes/${p.id}`, { preserveScroll: true });

    return (
        <div className="mb-4 break-inside-avoid space-y-3 rounded-xl border border-sidebar-border/70 bg-card p-4">
            <div className="flex items-start justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    {nota.fecha && (
                        <span className="inline-flex items-center gap-1">
                            <CalendarClock className="size-3" />
                            {formatDate(nota.fecha)}
                        </span>
                    )}
                    {nota.evento_titulo && (
                        <Badge variant="outline">{nota.evento_titulo}</Badge>
                    )}
                </div>
                <div className="flex gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={onEditar}
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7 text-destructive"
                        onClick={onEliminar}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </div>

            <p className="whitespace-pre-wrap text-sm">{nota.nota}</p>

            <div className="space-y-1 border-t pt-2">
                {nota.pendientes.map((p) => (
                        <div
                            key={p.id}
                            className="group flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={p.realizado}
                                onCheckedChange={() => alternar(p)}
                            />
                            <span
                                className={
                                    p.realizado
                                        ? 'flex-1 text-muted-foreground line-through'
                                        : 'flex-1'
                                }
                            >
                                {p.texto}
                            </span>
                            <button
                                type="button"
                                className="opacity-0 transition group-hover:opacity-100"
                                onClick={() => borrarPendiente(p)}
                            >
                                <X className="size-3.5 text-muted-foreground" />
                            </button>
                        </div>
                    ))}
                    <form className="flex gap-2 pt-1" onSubmit={agregar}>
                        <Input
                            value={nuevo}
                            placeholder="Agregar pendiente..."
                            className="h-8 text-sm"
                            onChange={(e) => setNuevo(e.target.value)}
                        />
                        <Button type="submit" size="sm" variant="outline">
                            <Plus className="size-4" />
                        </Button>
                    </form>
                </div>
        </div>
    );
}

NotasIndex.layout = {
    breadcrumbs: [{ title: 'Notas', href: '/notas' }],
};
