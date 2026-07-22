<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web
|--------------------------------------------------------------------------
| Acá solo queda el shell del SPA. Toda la API del panel se mudó a
| routes/panel.php (montada desde routes/api.php en /api y /api/v1), porque
| ahora, además del SPA, la consumen las apps móviles, que autentican con
| token Bearer y no con cookie de sesión. El SPA no se enteró del cambio: el
| guard sanctum sigue aceptando su cookie gracias a statefulApi().
*/

// Shell del SPA: cualquier ruta GET que no sea API/media/health devuelve el
// HTML de React, que se encarga del enrutado del lado del cliente.
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|media|up|build|storage).*$');
