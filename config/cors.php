<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Las apps nativas (iOS/Android) no hacen preflight ni CORS: esto solo
    | aplica a clientes que corran en un navegador. Hoy son dos casos:
    |
    |  - El SPA del panel, que es de MISMO origen y por lo tanto ni pasa por
    |    acá.
    |  - Una eventual build web de la app Flutter, servida desde otro dominio.
    |
    | Por eso los orígenes se declaran por .env (CORS_ALLOWED_ORIGINS, separados
    | por coma) en vez de dejar '*' fijo: con supports_credentials en true, el
    | comodín es inválido según la especificación y el navegador rechaza la
    | respuesta. Vacío = no se permite ningún origen cruzado, que es el estado
    | correcto mientras solo existan el SPA de mismo origen y las apps nativas.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 60 * 60,

    // Necesario para que un cliente web de otro origen pueda autenticarse por
    // cookie de sesión (el flujo stateful de Sanctum). Los clientes que usan
    // token Bearer no lo necesitan, pero tampoco les molesta.
    'supports_credentials' => true,

];
