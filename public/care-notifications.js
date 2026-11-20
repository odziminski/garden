self.addEventListener('push', (event) => {
    event.waitUntil(self.registration.showNotification('Bloomify', {
        body: 'You have plant checks or care tasks to review. Open Today.',
        tag: 'bloomify-daily-care',
        icon: new URL('images/icon-192.png', self.registration.scope).href,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const today = new URL('today', self.registration.scope).href;
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const existing = windows.find((window) => window.url === today);
        return existing ? existing.focus() : self.clients.openWindow(today);
    })());
});
