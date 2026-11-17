<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rutas con CORS
    |--------------------------------------------------------------------------
    |
    | La PWA (proyecto aparte, configurada en FRONTEND_URL) consume la API
    | JSON de /api/*. No hay rutas web para terceros.
    |
    */

    'paths' => ['api/*', 'up'],

    /*
    |--------------------------------------------------------------------------
    | Métodos permitidos
    |--------------------------------------------------------------------------
    |
    | La API usa GET/POST/PUT/DELETE (Route::apiResource y rutas de acción).
    | '*' cubre cualquier método actual o futuro sin tocar este archivo.
    |
    */

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Orígenes permitidos
    |--------------------------------------------------------------------------
    |
    | Solo la PWA. Se resuelve así:
    |  1. FRONTEND_URL            → origen de la PWA (contrato del backend).
    |  2. FRONTEND_URLS           → orígenes extra separados por coma
    |                               (p. ej. build de pruebas + producción).
    |  3. APP_ENV=local           → se suman localhost/127.0.0.1 en el
    |                               puerto de Vite por comodidad de desarrollo.
    |
    | Nunca '*' en producción: con tokens Bearer un origen abierto expone
    | la API a cualquier sitio. Si algún día se necesitan wildcards de
    | subdominios, usar 'allowed_origins_patterns'.
    |
    */

    'allowed_origins' => array_values(array_unique(array_filter([
        env('FRONTEND_URL', config('services.frontend.url')),
        ...array_map(
            fn (string $origen) => trim($origen),
            explode(',', (string) env('FRONTEND_URLS', '')),
        ),
        ...($appEnv = config('app.env')) === 'local' || $appEnv === 'testing'
            ? ['http://localhost:5173', 'http://127.0.0.1:5173']
            : [],
    ]))),

    'allowed_origins_patterns' => [
        // Ejemplo para producción con subdominios:
        // '#^https://[a-z0-9-]+\.mi-dominio\.edu\.mx$#',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cabeceras permitidas
    |--------------------------------------------------------------------------
    |
    | 'Authorization' (token Sanctum) y 'Content-Type' (JSON y multipart de
    | la foto del bien). '*' simplifica y evita romper el contrato cuando el
    | frontend agrega cabeceras de su client HTTP.
    |
    */

    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Cabeceras expuestas al JS de la PWA
    |--------------------------------------------------------------------------
    |
    | El envelope de la API es {data, ...}, todo viaja en el cuerpo. Si
    | algún día se paginara por cabeceras (X-Total-Count), agregarlas aquí.
    |
    */

    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Credenciales
    |--------------------------------------------------------------------------
    |
    | false: la PWA autentica con token Bearer, nunca con cookies. Además
    | 'supports_credentials' + 'allowed_origins: *' está prohibido por el
    | Fetch spec; mantener false mantiene el diseño simple y seguro.
    |
    */

    'supports_credentials' => false,

    /*
    |--------------------------------------------------------------------------
    | Cache del preflight
    |--------------------------------------------------------------------------
    |
    | El navegador guarda la respuesta OPTIONS una hora: una petición menos
    | en cada llamada de la PWA (importante en móvil/datos móviles).
    |
    */

    'max_age' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Preflight de rutas inexistentes
    |--------------------------------------------------------------------------
    |
    | Responde CORS al OPTIONS de rutas que no existen; útil mientras el
    | front ajusta su base URL y evita errores CORS confusos por 404.
    |
    */

    'preflight_continue' => false,

    'routes' => [],

    /*
    |--------------------------------------------------------------------------
    | Nota de operación
    |--------------------------------------------------------------------------
    |
    | Laravel 12 incluye HandleCors en el middleware global, así que basta
    | con este archivo de configuración (sin cambios en bootstrap/app.php).
    | El token viaja en 'Authorization'; el logout/rotación sigue el ciclo
    | de 480 min definido en SANCTUM_TOKEN_EXPIRATION.
    |
    */

];
