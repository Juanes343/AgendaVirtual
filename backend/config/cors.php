<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Orígenes permitidos para el Portal del Paciente.
    | supports_credentials debe ser true porque usamos Sanctum con cookies/tokens.
    | Cuando supports_credentials=true, allowed_origins NO puede ser ['*'].
    |
    */

    // Rutas vacías: CORS lo maneja public/index.php para evitar cabeceras duplicadas
    'paths' => [],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://siis09.simde.com.co',
        'https://devel82els.simde.com.co',
        'https://devel74.simde.com.co',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
