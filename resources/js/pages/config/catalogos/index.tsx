import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FolderCog, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { DataTable } from '@/components/data-table';
import type { DataTableColumn } from '@/components/data-table';
import { FormInputField } from '@/components/form-input-field';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldError } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { destroy, index, store } from '@/routes/config/catalogos';

const NINGUNO = '__none__';

type Campo = {
    name: string;
    label: string;
    type: 'text' | 'select';
    required: boolean;
};
type Opcion = { id: number; nombre: string };
type CatalogoItem = Record<string, string | number | null> & { id: number };
type Catalogo = {
    clave: string;
    label: string;
    campos: Campo[];
    items: CatalogoItem[];
    opciones: Record<string, Opcion[]>;
};
type FormData = Record<string, string | number | null>;

export default function CatalogosIndex({
    catalogos,
}: {
    catalogos: Catalogo[];
}) {
    const [activeClave, setActiveClave] = useState(catalogos[0]?.clave ?? '');
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const [deleteItem, setDeleteItem] = useState<CatalogoItem | null>(null);
    const form = useForm<FormData>({});
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success]);

    const activo = useMemo(
        () => catalogos.find((c) => c.clave === activeClave) ?? catalogos[0],
        [catalogos, activeClave],
    );

    if (!activo) {
        return (
            <>
                <Head title="Catálogos" />
                <p className="p-4 text-sm text-muted-foreground">
                    No hay catálogos configurados.
                </p>
            </>
        );
    }

    const valorInicial = (item: CatalogoItem | null): FormData => {
        const data: FormData = { id: item?.id ?? null };

        for (const campo of activo.campos) {
            data[campo.name] = item?.[campo.name] ?? '';
        }

        return data;
    };

    const openCreate = () => {
        form.clearErrors();
        form.setData(valorInicial(null));
        setFormMode('create');
    };

    const openEdit = (item: CatalogoItem) => {
        form.clearErrors();
        form.setData(valorInicial(item));
        setFormMode('edit');
    };

    const closeForm = (open: boolean) => {
        if (!open) {
            setFormMode(null);
            form.clearErrors();
        }
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => {
            // Normaliza los select "ninguno" a null antes de enviar.
            const salida: FormData = { ...data };

            for (const campo of activo.campos) {
                if (campo.type === 'select' && salida[campo.name] === NINGUNO) {
                    salida[campo.name] = null;
                }
            }

            return salida;
        });

        form.post(store.url(activo.clave), {
            preserveScroll: true,
            onSuccess: () => {
                setFormMode(null);
                form.reset();
            },
            onError: (errors) =>
                toast.error(
                    resolveFormErrorMessage(
                        errors,
                        'Verifica los campos marcados.',
                    ),
                ),
        });
    };

    const columns: DataTableColumn<CatalogoItem>[] = [
        ...activo.campos.map(
            (campo): DataTableColumn<CatalogoItem> => ({
                key: campo.name,
                header: campo.label,
                accessor: (row) => row[campo.name],
                cell: (row) => {
                    if (campo.type === 'select') {
                        const opciones = activo.opciones[campo.name] ?? [];
                        const encontrada = opciones.find(
                            (o) => o.id === row[campo.name],
                        );

                        return encontrada?.nombre ?? '—';
                    }

                    return (row[campo.name] as string) || '—';
                },
            }),
        ),
        {
            key: 'actions',
            header: '',
            className: 'w-14',
            cell: (row) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-8">
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => openEdit(row)}>
                            <Pencil className="mr-2 size-4" /> Editar
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={() => setDeleteItem(row)}
                        >
                            <Trash2 className="mr-2 size-4" /> Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Catálogos" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center gap-3">
                        <FolderCog className="size-5 text-primary" />
                        <div>
                            <h1 className="text-xl font-semibold">Catálogos</h1>
                            <p className="text-sm text-muted-foreground">
                                Administra los catálogos base del sistema.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Pestañas de catálogos */}
                <div className="flex flex-wrap gap-2">
                    {catalogos.map((catalogo) => (
                        <Button
                            key={catalogo.clave}
                            size="sm"
                            variant={
                                catalogo.clave === activo.clave
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => setActiveClave(catalogo.clave)}
                        >
                            {catalogo.label}
                            <span className="ml-2 rounded-full bg-background/20 px-1.5 text-xs">
                                {catalogo.items.length}
                            </span>
                        </Button>
                    ))}
                </div>

                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-medium">{activo.label}</h2>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 size-4" /> Nuevo
                    </Button>
                </div>

                <DataTable
                    key={activo.clave}
                    columns={columns}
                    data={activo.items}
                    searchColumn="nombre"
                    searchPlaceholder="Buscar por nombre..."
                />
            </div>

            <CrudFormDialog
                open={formMode !== null}
                onOpenChange={closeForm}
                title={
                    formMode === 'edit'
                        ? `Editar — ${activo.label}`
                        : `Nuevo — ${activo.label}`
                }
                description="Completa los datos del registro."
                submitLabel={formMode === 'edit' ? 'Guardar cambios' : 'Guardar'}
                processing={form.processing}
                onSubmit={submit}
            >
                {activo.campos.map((campo) => {
                    const valor = form.data[campo.name];
                    const error = form.errors[campo.name];

                    if (campo.type === 'select') {
                        const opciones = activo.opciones[campo.name] ?? [];

                        return (
                            <Field key={campo.name}>
                                <Label htmlFor={`campo-${campo.name}`}>
                                    {campo.label}
                                </Label>
                                <Select
                                    value={
                                        valor === null ||
                                        valor === '' ||
                                        valor === undefined
                                            ? NINGUNO
                                            : String(valor)
                                    }
                                    onValueChange={(v) =>
                                        form.setData(
                                            campo.name,
                                            v === NINGUNO ? null : Number(v),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id={`campo-${campo.name}`}
                                        className="w-full"
                                        aria-invalid={Boolean(error)}
                                    >
                                        <SelectValue placeholder="Selecciona..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NINGUNO}>
                                            — Ninguno —
                                        </SelectItem>
                                        {opciones.map((opcion) => (
                                            <SelectItem
                                                key={opcion.id}
                                                value={String(opcion.id)}
                                            >
                                                {opcion.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {error && <FieldError>{error}</FieldError>}
                            </Field>
                        );
                    }

                    return (
                        <FormInputField
                            key={campo.name}
                            id={`campo-${campo.name}`}
                            label={campo.label}
                            value={(valor as string) ?? ''}
                            error={error}
                            onChange={(event) =>
                                form.setData(campo.name, event.target.value)
                            }
                        />
                    );
                })}
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={deleteItem !== null}
                onOpenChange={(open) => !open && setDeleteItem(null)}
                title="Eliminar registro"
                entityLabel="el registro"
                itemName={
                    deleteItem ? String(deleteItem.nombre ?? '') : undefined
                }
                onConfirm={() => {
                    if (!deleteItem) {
                        return;
                    }

                    router.delete(
                        destroy.url({
                            catalogo: activo.clave,
                            id: deleteItem.id,
                        }),
                        {
                            preserveScroll: true,
                            onSuccess: () => setDeleteItem(null),
                            onError: (errors) =>
                                toast.error(
                                    resolveFormErrorMessage(
                                        errors,
                                        'No se pudo eliminar el registro.',
                                    ),
                                ),
                        },
                    );
                }}
            />
        </>
    );
}

CatalogosIndex.layout = {
    breadcrumbs: [
        { title: 'Configuración', href: index() },
        { title: 'Catálogos', href: index() },
    ],
};
