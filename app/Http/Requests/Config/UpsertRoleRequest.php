<?php

namespace App\Http\Requests\Config;

use App\Enums\Rol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class UpsertRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roleId = $this->integer('id') ?: null;
        $nameRule = Rule::unique('roles', 'name');

        if ($roleId !== null) {
            $nameRule->ignore($roleId);
        }

        return [
            'id' => ['nullable', 'integer', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255', $nameRule],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * Los roles base del seeder no se pueden renombrar: el código y las
     * futuras corridas del seeder los referencian por nombre.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('id')) {
                return;
            }

            $rol = Role::query()->find($this->integer('id'));

            if ($rol !== null && Rol::tryFrom($rol->name) !== null && $rol->name !== $this->string('name')->toString()) {
                $validator->errors()->add('name', 'Los roles base de la plataforma no se pueden renombrar.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un rol con este nombre.',
        ];
    }
}
