<?php

namespace App\Http\Controllers\Config;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Config\UpsertRoleRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $roles = Role::query()
            ->with('permissions:id,name,guard_name')
            ->when($busqueda !== '', fn (Builder $query) => $query->whereLike('name', "%{$busqueda}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('config/roles/index', [
            'roles' => $roles->getCollection()->map(fn (Role $rol): array => [
                'id' => $rol->id,
                'name' => $rol->name,
                'guard_name' => $rol->guard_name,
                'created_at' => $rol->created_at?->toISOString(),
                'permissions' => $rol->permissions->map(fn (Permission $permiso): array => [
                    'id' => $permiso->id,
                    'name' => $permiso->name,
                    'guard_name' => $permiso->guard_name,
                ])->values(),
            ])->values(),
            'paginacion' => [
                'total' => $roles->total(),
                'currentPage' => $roles->currentPage(),
                'lastPage' => $roles->lastPage(),
                'prevUrl' => $roles->previousPageUrl(),
                'nextUrl' => $roles->nextPageUrl(),
                'busqueda' => $busqueda,
            ],
            'permissions' => Permission::query()
                ->orderBy('name')
                ->get(['id', 'name', 'guard_name']),
        ]);
    }

    public function store(UpsertRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = isset($data['id']) ? Role::query()->findOrFail((int) $data['id']) : new Role(['guard_name' => 'web']);

        $role->name = $data['name'];
        $role->save();
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', isset($data['id']) ? 'Rol actualizado correctamente.' : 'Rol creado correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Los roles base del seeder no se eliminan; un rol con usuarios
        // asignados tampoco (primero hay que reasignarlos).
        if (Rol::tryFrom($role->name) !== null) {
            return back()->with('error', 'Los roles base de la plataforma no se pueden eliminar.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'El rol tiene usuarios asignados; reasígnalos antes de eliminarlo.');
        }

        $role->delete();

        return back()->with('success', 'Rol eliminado correctamente.');
    }
}
