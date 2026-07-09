import { Head, router, useForm, usePage } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2, UserPlus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { DataTable } from '@/components/data-table';
import type { DataTableColumn, DataTableServer } from '@/components/data-table';
import { FormInputField } from '@/components/form-input-field';
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
import { formatDate } from '@/lib/date';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { destroy, index, store } from '@/routes/config/users';

type UserRole = { id: number; name: string };
type UserRow = {
    id: number;
    name: string;
    email: string;
    es_super_admin: boolean;
    roles: UserRole[];
    created_at: string;
};
type UserForm = {
    id: number | null;
    name: string;
    email: string;
    password: string;
    roles: string[];
};

export default function UsersIndex({
    users,
    paginacion,
    roles,
}: {
    users: UserRow[];
    paginacion: DataTableServer;
    roles: UserRole[];
}) {
    const [activeUser, setActiveUser] = useState<UserRow | null>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const form = useForm<UserForm>({
        id: null,
        name: '',
        email: '',
        password: '',
        roles: [],
    });
    const { auth, flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success]);

    const toggleRole = (roleName: string) => {
        const selectedRoles = form.data.roles;
        form.setData(
            'roles',
            selectedRoles.includes(roleName)
                ? selectedRoles.filter((value) => value !== roleName)
                : [...selectedRoles, roleName],
        );
    };

    const openCreateDialog = () => {
        setActiveUser(null);
        form.reset();
        form.clearErrors();
        setFormMode('create');
    };

    const openEditDialog = (user: UserRow) => {
        setActiveUser(user);
        form.clearErrors();
        form.setData({
            id: user.id,
            name: user.name,
            email: user.email,
            password: '',
            roles: user.roles.map((role) => role.name),
        });
        setFormMode('edit');
    };

    const closeFormDialog = (open: boolean) => {
        if (!open) {
            setFormMode(null);
            setActiveUser(null);
            form.clearErrors();
        }
    };

    const submitForm = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setFormMode(null);
                setActiveUser(null);
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

    const columns: DataTableColumn<UserRow>[] = [
        {
            key: 'name',
            header: 'Nombre',
            accessor: (row) => row.name,
            cell: (row) => row.name,
        },
        {
            key: 'email',
            header: 'Correo',
            accessor: (row) => row.email,
            cell: (row) => row.email,
        },
        {
            key: 'roles',
            header: 'Roles',
            cell: (row) => (
                <div className="flex flex-wrap gap-1">
                    {row.es_super_admin ? (
                        <Badge>Super admin</Badge>
                    ) : row.roles.length > 0 ? (
                        row.roles.map((role) => (
                            <Badge key={role.id} variant="outline">
                                {role.name}
                            </Badge>
                        ))
                    ) : (
                        <Badge variant="secondary">Sin rol</Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'created_at',
            header: 'Creado',
            cell: (row) => formatDate(row.created_at),
        },
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
                        <DropdownMenuItem onClick={() => openEditDialog(row)}>
                            <Pencil className="mr-2 size-4" /> Editar
                        </DropdownMenuItem>
                        {row.id !== auth.user.id && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => setActiveUser(row)}
                                >
                                    <Trash2 className="mr-2 size-4" /> Eliminar
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Usuarios" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <Users className="size-5 text-primary" />
                            <div>
                                <h1 className="text-xl font-semibold">
                                    Usuarios
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Gestiona cuentas y roles de la plataforma.
                                </p>
                            </div>
                        </div>
                        <Button onClick={openCreateDialog}>
                            <UserPlus className="mr-2 size-4" /> Nuevo usuario
                        </Button>
                    </div>
                </div>
                <DataTable
                    columns={columns}
                    data={users}
                    server={paginacion}
                    searchPlaceholder="Buscar usuario por nombre o correo..."
                />
            </div>

            <CrudFormDialog
                open={formMode !== null}
                onOpenChange={closeFormDialog}
                title={formMode === 'edit' ? 'Editar usuario' : 'Crear usuario'}
                description={
                    formMode === 'edit'
                        ? 'Actualiza los datos y roles del usuario seleccionado.'
                        : 'Completa los datos y asigna uno o más roles.'
                }
                submitLabel={
                    formMode === 'edit' ? 'Guardar cambios' : 'Guardar usuario'
                }
                processing={form.processing}
                onSubmit={submitForm}
            >
                <FormInputField
                    id="user-name"
                    label="Nombre"
                    value={form.data.name}
                    error={form.errors.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="Ej. Ana López"
                />

                <FormInputField
                    id="user-email"
                    type="email"
                    label="Correo"
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                    placeholder="ana@campana.mx"
                />

                <FormInputField
                    id="user-password"
                    type="password"
                    label={
                        formMode === 'edit'
                            ? 'Contraseña (opcional)'
                            : 'Contraseña'
                    }
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                    placeholder={
                        formMode === 'edit'
                            ? 'Deja vacío para mantener la actual'
                            : 'Mínimo 8 caracteres'
                    }
                />

                <Field>
                    <p className="text-sm font-medium">Roles</p>
                    <div className="grid gap-2 md:grid-cols-2">
                        {roles.map((role) => (
                            <label
                                key={role.id}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={form.data.roles.includes(
                                        role.name,
                                    )}
                                    onCheckedChange={() =>
                                        toggleRole(role.name)
                                    }
                                />
                                {role.name}
                            </label>
                        ))}
                    </div>
                    {form.errors.roles && (
                        <FieldError>{form.errors.roles}</FieldError>
                    )}
                </Field>
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={formMode === null && activeUser !== null}
                onOpenChange={(open) => !open && setActiveUser(null)}
                title="Eliminar usuario"
                entityLabel="la cuenta de"
                itemName={activeUser?.name}
                onConfirm={() => {
                    if (!activeUser) {
                        return;
                    }

                    router.delete(destroy.url(activeUser.id), {
                        preserveScroll: true,
                        onSuccess: () => setActiveUser(null),
                        onError: (errors) =>
                            toast.error(
                                resolveFormErrorMessage(
                                    errors,
                                    'No se pudo eliminar el usuario.',
                                ),
                            ),
                    });
                }}
            />
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Configuración', href: index() },
        { title: 'Usuarios', href: index() },
    ],
};
