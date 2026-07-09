<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseña inicial del super administrador
    |--------------------------------------------------------------------------
    |
    | La usa el DatabaseSeeder al crear el usuario principal. En producción
    | es obligatoria; en desarrollo local cae a un valor por defecto débil.
    |
    */

    'admin_seed_password' => env('ADMIN_SEED_PASSWORD'),

];
