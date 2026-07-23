# Puesta en producción de notificaciones Android

El código de la aplicación y del backend ya usa FCM HTTP v1. Para que una
compilación instalada reciba avisos con la aplicación cerrada, el proyecto de
Firebase debe contener una aplicación Android con este package name:

```text
com.cloudapicc.cloud_api_cc
```

## Aplicación Flutter

1. Descargar `google-services.json` desde Firebase.
2. Guardarlo en `mobile-app/android/app/google-services.json`.
3. Generar la APK o el App Bundle. Las compilaciones `release` fallan a
   propósito cuando falta ese archivo, para evitar publicar una app sin push.

## Backend Laravel

Configurar en el `.env` de producción una cuenta de servicio del mismo
proyecto Firebase:

```dotenv
APP_TIMEZONE=America/La_Paz
QUEUE_CONNECTION=database
FCM_PROJECT_ID=el-id-del-proyecto
FCM_CREDENTIALS_PATH=/ruta/privada/firebase-service-account.json
FCM_CHANNEL_ID=chats
```

Después de desplegar:

```text
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Debe existir permanentemente al menos un worker de colas, administrado por el
servicio del servidor (Supervisor, systemd o el panel del hosting):

```text
php artisan queue:work --tries=3 --backoff=10 --timeout=60
```

Sin el worker, los avisos quedan almacenados en la tabla `jobs` pero no se
envían a Firebase.

## Prueba mínima

1. Iniciar sesión en dos teléfonos Android con dos agentes que estén dentro de
   su horario.
2. Cerrar ambas aplicaciones y enviar un WhatsApp nuevo.
3. Verificar que ambos teléfonos reciban el aviso.
4. Abrirlo en el primer teléfono: debe mostrarse el chat.
5. El aviso debe desaparecer del segundo teléfono. Si se alcanza a tocar antes
   de desaparecer, debe verse “El chat ya fue tomado por otro agente”.
