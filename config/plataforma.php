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

    /*
    |--------------------------------------------------------------------------
    | Schema legacy (migración de datos)
    |--------------------------------------------------------------------------
    |
    | Nombre del schema PostgreSQL de la Suite FIEL en PHP puro desde el que
    | los seeders ETL copian los datos. Vive en la misma base de datos.
    |
    */

    'legacy_schema' => env('SUITE_LEGACY_SCHEMA', 'hgo_pachuca'),

];
