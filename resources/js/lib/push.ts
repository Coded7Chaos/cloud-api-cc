import { api } from './api';

export function isPushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

export async function subscribeToPushNotifications() {
    if (!isPushSupported()) {
        throw new Error('Este navegador no soporta notificaciones push.');
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new Error('No se concedió permiso para enviar notificaciones.');
    }

    const keyRes = await api.get('/push/public-key');
    const publicKey = keyRes.data.public_key;

    if (!publicKey) {
        throw new Error('Las claves Web Push todavía no están configuradas en el servidor.');
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    const existing = await registration.pushManager.getSubscription();
    const subscription =
        existing ??
        (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        }));

    await api.post('/push/subscriptions', subscription.toJSON());

    return subscription;
}

function urlBase64ToUint8Array(base64String: string) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const output = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; i++) {
        output[i] = rawData.charCodeAt(i);
    }

    return output;
}
