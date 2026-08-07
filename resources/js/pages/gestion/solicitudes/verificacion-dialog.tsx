import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import type { Solicitud, Verificacion } from './types';

type Form = {
    fecha: string;
    atendido: number | null;
    satisfecho: number | null;
    comentario: string;
};

/** Pantalla "Verificar atención de solicitud" del legacy (`vrfccn.php`). */
export function VerificacionDialog({
    solicitud,
    onClose,
}: {
    solicitud: Solicitud;
    onClose: () => void;
}) {
    const [previas, setPrevias] = useState<Verificacion[]>([]);
    const form = useForm<Form>({
        fecha: '',
        atendido: null,
        satisfecho: null,
        comentario: '',
    });

    const cargar = () => {
        fetch(`/gestion/solicitudes/${solicitud.id}/verificaciones`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data) => setPrevias(data as Verificacion[]));
    };

    useEffect(cargar, [solicitud.id]);

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/gestion/solicitudes/${solicitud.id}/verificaciones`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                cargar();
            },
            onError: (errores) =>
                toast.error(
                    resolveFormErrorMessage(
                        errores,
                        'Verifica los campos marcados.',
                    ),
                ),
        });
    };

    const radios = (
        campo: 'atendido' | 'satisfecho',
        opciones: { valor: number; etiqueta: string }[],
    ) => (
        <div className="flex flex-wrap gap-4">
            {opciones.map(({ valor, etiqueta }) => (
                <label key={valor} className="flex items-center gap-2 text-sm">
                    <input
                        type="radio"
                        name={campo}
                        className="size-4"
                        checked={form.data[campo] === valor}
                        onChange={() => form.setData(campo, valor)}
                    />
                    {etiqueta}
                </label>
            ))}
        </div>
    );

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="lg"
            showFooter={false}
            title={
                solicitud.status === 6
                    ? 'Solicitud con atención rápida'
                    : 'Solicitud atendida'
            }
            description={solicitud.solicitud ?? ''}
        >
            <form className="space-y-4" onSubmit={enviar}>
                <p className="text-sm font-semibold text-destructive">
                    Verificar atención de solicitud
                </p>

                <div className="grid gap-4 sm:grid-cols-3">
                    <FormInputField
                        id="vrf-fecha"
                        type="date"
                        label="Fecha de verificación"
                        value={form.data.fecha}
                        error={form.errors.fecha}
                        onChange={(e) => form.setData('fecha', e.target.value)}
                    />
                    <Field>
                        <Label>¿Solicitud atendida?</Label>
                        {radios('atendido', [
                            { valor: 1, etiqueta: 'Sí' },
                            { valor: 0, etiqueta: 'No' },
                        ])}
                    </Field>
                    <Field>
                        <Label>¿Ciudadano satisfecho con la respuesta?</Label>
                        {radios('satisfecho', [
                            { valor: 1, etiqueta: 'Sí' },
                            { valor: 2, etiqueta: 'Poco' },
                            { valor: 3, etiqueta: 'No' },
                        ])}
                    </Field>
                </div>

                <Field>
                    <Label htmlFor="vrf-comentario">Comentario</Label>
                    <textarea
                        id="vrf-comentario"
                        className="min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        placeholder="COMENTARIO"
                        value={form.data.comentario}
                        onChange={(e) =>
                            form.setData('comentario', e.target.value)
                        }
                    />
                </Field>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        <Send className="mr-2 size-4" /> Enviar
                    </Button>
                </div>
            </form>

            {previas.length > 0 && (
                <div className="space-y-2 border-t pt-4">
                    <p className="text-sm font-semibold">
                        Verificaciones ({previas.length})
                    </p>
                    {previas.map((v) => (
                        <div
                            key={v.id}
                            className="rounded-md border p-3 text-sm"
                        >
                            <p className="whitespace-pre-wrap">{v.comentario}</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {v.fecha ?? ''}
                                {v.satisfecho_label
                                    ? ` · Satisfacción: ${v.satisfecho_label}`
                                    : ''}
                                {v.autor ? ` · ${v.autor}` : ''}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </CrudFormDialog>
    );
}
