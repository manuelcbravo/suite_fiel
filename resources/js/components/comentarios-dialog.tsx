import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Label } from '@/components/ui/label';

type Comentario = {
    id: number;
    comentario: string;
    autor: string | null;
    fecha: string | null;
};
export type ComentarioTipo =
    | 'beneficiarios'
    | 'organizaciones'
    | 'proveedores';

export function ComentariosDialog({
    tipo,
    id,
    nombre,
    onClose,
}: {
    tipo: ComentarioTipo;
    id: number;
    nombre: string;
    onClose: () => void;
}) {
    const [comentarios, setComentarios] = useState<Comentario[]>([]);
    const form = useForm<{ comentario: string }>({ comentario: '' });
    const base = `/directorio/${tipo}/${id}/comentarios`;

    const cargar = () => {
        fetch(base, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((d) => setComentarios(d as Comentario[]));
    };

    useEffect(cargar, [base]);

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(base, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                cargar();
            },
            onError: () => toast.error('No se pudo agregar el comentario.'),
        });
    };

    const eliminar = (comentarioId: number) =>
        router.delete(`/directorio/comentarios/${comentarioId}`, {
            preserveScroll: true,
            onSuccess: cargar,
            onError: () => toast.error('No se pudo eliminar.'),
        });

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="md"
            showFooter={false}
            title="Comentarios"
            description={nombre}
        >
            <div className="space-y-4">
                <div className="space-y-2">
                    {comentarios.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Sin comentarios.
                        </p>
                    )}
                    {comentarios.map((c) => (
                        <div
                            key={c.id}
                            className="group flex items-start justify-between gap-2 rounded-md border p-3 text-sm"
                        >
                            <div>
                                <p className="whitespace-pre-wrap">
                                    {c.comentario}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {c.autor ?? 'Sistema'}
                                    {c.fecha ? ` · ${c.fecha}` : ''}
                                </p>
                            </div>
                            <button
                                type="button"
                                className="opacity-0 transition group-hover:opacity-100"
                                onClick={() => eliminar(c.id)}
                            >
                                <Trash2 className="size-4 text-destructive" />
                            </button>
                        </div>
                    ))}
                </div>

                <form className="space-y-2 border-t pt-4" onSubmit={enviar}>
                    <Field>
                        <Label htmlFor="nuevo-comentario">
                            Nuevo comentario
                        </Label>
                        <textarea
                            id="nuevo-comentario"
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={form.data.comentario}
                            onChange={(e) =>
                                form.setData('comentario', e.target.value)
                            }
                        />
                    </Field>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        Agregar
                    </Button>
                </form>
            </div>
        </CrudFormDialog>
    );
}
