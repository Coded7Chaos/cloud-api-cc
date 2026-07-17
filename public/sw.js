self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    const fallback = {
        title: 'Cloud API CC',
        body: 'Tienes un nuevo mensaje.',
        url: '/chats',
    };

    let payload = fallback;
    if (event.data) {
        try {
            payload = { ...fallback, ...event.data.json() };
        } catch {
            payload = { ...fallback, body: event.data.text() };
        }
    }

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            data: {
                url: payload.url || payload.data?.url || '/chats',
            },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/chats';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }

            return self.clients.openWindow(url);
        }),
    );
});
