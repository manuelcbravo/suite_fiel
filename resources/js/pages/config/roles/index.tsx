import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    MoreHorizontal,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { DataTable } from '@/components/data-table';
import type { DataTableColumn, DataTableServer } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/date';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { destroy, index, store } from '@/routes/config/roles';

type Permission = { id: number; name: string; guard_name: string };
type Role = {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    permissions: Permission[];
};
type RoleForm = { id: number | null; name: string; permissions: string[] };

export default function RolesIndex({
    roles,
    paginacion,
    permissions,
}: {
    roles: Role[];
    paginacion: DataTableServer;
    permissions: Permission[];
}) {
    const [activeRole, setActiveRole] = useState<Role | null>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const form = useForm<RoleForm>({ id: null, name: '', permissions: [] });
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success]);

    const groupedPermissions = useMemo(
        () =>
            permissions.reduce<Record<string, Permission[]>>(
                (acc, permission) => {
                    const [group] = permission.name.split('.');
                    const key = group ?? 'general';

                    if (!acc[key]) {
                        acc[key] = [];
                    }

                    acc[key].push(permission);

                    return acc;
                },
                {},
            ),
        [permissions],
    );

    const togglePermission = (permissionName: string) => {
        const current = form.data.permissions;
        form.setData(
            'permissions',
            current.includes(permissionName)
                ? current.filter((permission) => permission !== permissionName)
                : [...current, permissionName],
        );
    };

    const openCreateDialog = () => {
        setActiveRole(null);
        form.reset();
        form.clearErrors();
        setFormMode('create');
    };

    const openEditDialog = (role: Role) => {
        setActiveRole(role);
        form.clearErrors();
        form.setData({
            id: role.id,
            name: role.name,
            permissions: role.permissions.map((permission) => permission.name),
        });
        setFormMode('edit');
    };

    const closeFormDialog = (open: boolean) => {
        if (!open) {
            setFormMode(null);
            setActiveRole(null);
            form.clearErrors();
        }
    };

    const submitForm = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setFormMode(null);
                setActiveRole(null);
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

    const columns: DataTableColumn<Role>[] = [
        {
            key: 'name',
            header: 'Rol',
            accessor: (role) => role.name,
            cell: (role) => role.name,
        },
        {
            key: 'permissions',
            header: 'Permisos',
            cell: (role) => (
                <div className="flex flex-wrap gap-1">
                    {role.permissions.slice(0, 3).map((permission) => (
                        <Badge key={permission.id} variant="outline">
                            {permission.name}
                        </Badge>
                    ))}
                    {role.permissions.length > 3 && (
                        <Badge variant="secondary">
                            +{role.permissions.length - 3}
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'created_at',
            header: 'Creado',
            cell: (role) => formatDate(role.created_at),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-14',
            cell: (role) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-8">
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => openEditDialog(role)}>
                            <Pencil className="mr-2 size-4" /> Editar
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={() => setActiveRole(role)}
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
            <Head title="Roles y permisos" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <ShieldCheck className="size-5 text-primary" />
                            <div>
                                <h1 className="text-xl font-semibold">
                                    Roles y permisos
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Define perfiles con permisos granulares para
                                    controlar acceso.
                                </p>
                            </div>
                        </div>
                        <Button onClick={openCreateDialog}>
                            <Plus className="mr-2 size-4" /> Nuevo rol
                        </Button>
                    </div>
                </div>
                <DataTable
                    columns={columns}
                    data={roles}
                    server={paginacion}
                    searchPlaceholder="Buscar rol..."
                />
            </div>

            <CrudFormDialog
                open={formMode !== null}
                onOpenChange={closeFormDialog}
                title={formMode === 'edit' ? 'Editar rol' : 'Crear rol'}
                description={
                    formMode === 'edit'
                        ? 'Ajusta el nombre y permisos del rol seleccionado.'
                        : 'Crea un rol y asigna los permisos necesarios.'
                }
                submitLabel={
                    formMode === 'edit' ? 'Guardar cambios' : 'Guardar rol'
                }
                processing={form.processing}
                onSubmit={submitForm}
            >
                <Field>
                    <Label htmlFor="role-name">Nombre del rol</Label>
                    <Input
                        id="role-name"
                        aria-invalid={Boolean(form.errors.name)}
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="Ej. supervisor"
                    />
                    {form.errors.name && (
                        <FieldError>{form.errors.name}</FieldError>
                    )}
                </Field>

                <Field>
                    <p className="text-sm font-medium">Permisos</p>
                    <div className="grid max-h-80 gap-3 overflow-y-auto md:grid-cols-3">
                        {Object.entries(groupedPermissions).map(
                            ([group, grouped]) => (
                                <div
                                    key={group}
                                    className="rounded-lg border p-3"
                                >
                                    <p className="mb-2 text-sm font-semibold capitalize">
                                        {group}
                                    </p>
                                    <div className="space-y-2">
                                        {grouped.map((permission) => (
                                            <label
                                                key={permission.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <Checkbox
                                                    checked={form.data.permissions.includes(
                                                        permission.name,
                                                    )}
                                                    onCheckedChange={() =>
                                                        togglePermission(
                                                            permission.name,
                                                        )
                                                    }
                                                />
                                                {permission.name}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ),
                        )}
                    </div>
                    {form.errors.permissions && (
                        <FieldError>{form.errors.permissions}</FieldError>
                    )}
                </Field>
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={formMode === null && activeRole !== null}
                onOpenChange={(open) => !open && setActiveRole(null)}
                title="Eliminar rol"
                entityLabel="el rol"
                itemName={activeRole?.name}
                onConfirm={() => {
                    if (!activeRole) {
                        return;
                    }

                    router.delete(destroy.url(activeRole.id), {
                        preserveScroll: true,
                        onSuccess: () => setActiveRole(null),
                        onError: (errors) =>
                            toast.error(
                                resolveFormErrorMessage(
                                    errors,
                                    'No se pudo eliminar el rol.',
                                ),
                            ),
                    });
                }}
            />
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Configuración', href: index() },
        { title: 'Roles y permisos', href: index() },
    ],
};
