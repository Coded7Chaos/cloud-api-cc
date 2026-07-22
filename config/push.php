<?php

use App\Services\Push\ApnsChannel;
use App\Services\Push\FcmChannel;
use App\Services\Push\WebPushChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Canales activos
    |--------------------------------------------------------------------------
    |
    | Cada notificación se reparte a todos los canales de esta lista. Los que
    | no tengan credenciales se saltean solos (isConfigured()), así que en
    | desarrollo no hace falta configurar nada para que la app funcione.
    |
    */

    'channels' => [
        WebPushChannel::class,
        ApnsChannel::class,
        FcmChannel::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | APNs (iOS)
    |--------------------------------------------------------------------------
    |
    | Credenciales del Apple Developer Program. Se necesita una clave de
    | autenticación (.p8) creada en Certificates, Identifiers & Profiles ->
    | Keys, con el servicio "Apple Push Notifications" habilitado.
    |
    |   key_id      El Key ID de esa clave (10 caracteres).
    |   team_id     El Team ID de la cuenta (arriba a la derecha en el portal).
    |   bundle_id   El bundle identifier de la app iOS. Va como apns-topic.
    |   production  false usa el entorno sandbox, que es el que reciben las
    |               builds instaladas desde Xcode. Las de TestFlight y App
    |               Store usan producción.
    |
    | La clave puede darse pegada en APNS_PRIVATE_KEY (con \n literales) o por
    | ruta de archivo en APNS_PRIVATE_KEY_PATH.
    |
    */

    'apns' => [
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID'),
        'bundle_id' => env('APNS_BUNDLE_ID'),
        'private_key' => env('APNS_PRIVATE_KEY'),
        'private_key_path' => env('APNS_PRIVATE_KEY_PATH'),
        'production' => (bool) env('APNS_PRODUCTION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | FCM (Android)
    |--------------------------------------------------------------------------
    |
    | Service account del proyecto de Firebase (Configuración del proyecto ->
    | Cuentas de servicio -> Generar nueva clave privada). El envío de
    | mensajes por FCM no tiene costo.
    |
    | El JSON puede darse pegado en FCM_CREDENTIALS o por ruta en
    | FCM_CREDENTIALS_PATH. El project_id se toma del propio JSON si no se
    | declara aparte.
    |
    | channel_id tiene que coincidir con el NotificationChannel que crea la
    | app Android; si no coincide, Android entrega la notificación en un canal
    | por defecto y el usuario no puede configurarla.
    |
    */

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env('FCM_CREDENTIALS'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH'),
        'channel_id' => env('FCM_CHANNEL_ID', 'chats'),
    ],

];
