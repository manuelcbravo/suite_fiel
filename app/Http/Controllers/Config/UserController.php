<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Config\UpsertUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();
        $esSuperAdmin = (bool) $request->user()?->es_super_admin;

        $usuarios = User::query()
            ->with('roles:id,name')
            ->select(['id', 'name', 'email', 'es_super_admin', 'created_at'])
            // Los super admins solo son visibles (y gestionables) entre sí.
            ->when(! $esSuperAdmin, fn (Builder $query) => $query->where('es_super_admin', false))
            ->when($busqueda !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$busqueda}%")
                    ->orWhereLike('email', "%{$busqueda}%"),
            ))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('config/users/index', [
            'users' => $usuarios->getCollection()->map(fn (User $usuario): array => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'es_super_admin' => $usuario->es_super_admin,
                'roles' => $usuario->roles->map(fn ($rol): array => [
                    'id' => (int) $rol->getKey(),
                    'name' => (string) $rol->getAttribute('name'),
                ])->all(),
                'created_at' => $usuario->created_at?->toISOString(),
            ])->values(),
            'paginacion' => [
                'total' => $usuarios->total(),
                'currentPage' => $usuarios->currentPage(),
                'lastPage' => $usuarios->lastPage(),
                'prevUrl' => $usuarios->previousPageUrl(),
                'nextUrl' => $usuarios->nextPageUrl(),
                'busqueda' => $busqueda,
            ],
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(UpsertUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = isset($data['id']) ? User::query()->findOrFail((int) $data['id']) : new User;

        // Un usuario que no es super admin no puede tocar la cuenta de un
        // super admin (cambiar su correo o contraseña sería tomar su cuenta).
        if ($user->exists && $user->es_super_admin && ! (bool) $request->user()?->es_super_admin) {
            return back()->with('error', 'No puedes modificar a un super administrador.');
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->fill($payload);
        $user->save();
        $user->syncRoles($data['roles'] ?? []);

        return back()->with('success', isset($data['id']) ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()?->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($user->es_super_admin && ! (bool) $request->user()?->es_super_admin) {
            return back()->with('error', 'No puedes eliminar a un super administrador.');
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado correctamente.');
    }
}
