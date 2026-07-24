// === Skedd Service Worker ===
var CACHE_NAME = 'skedd-v1';

// Instalação: ativa imediatamente sem esperar aba fechar
self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
});

// Recebe push e exibe notificação usando o payload enviado pelo servidor
self.addEventListener('push', function(event) {
    var data = {};

    // Tenta ler o payload JSON enviado diretamente no push
    if (event.data) {
        try {
            data = event.data.json();
        } catch(e) {
            data = { title: 'Skedd', body: event.data.text() || 'Nova notificação.' };
        }
    } else {
        data = { title: 'Skedd', body: 'Você tem novas avaliações.' };
    }

    var title = data.title || 'Skedd';
    var options = {
        body: data.body || 'Verifique sua agenda.',
        icon: 'https://i.ibb.co/ymJC5sNN/Captura-de-tela-2026-05-19-100134-1.webp',
        badge: 'https://i.ibb.co/ymJC5sNN/Captura-de-tela-2026-05-19-100134-1.webp',
        vibrate: [200, 100, 200],
        tag: 'skedd-notif',           // agrupa notificações repetidas
        renotify: true,
        data: { url: data.url || 'agenda.php' }
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Clique na notificação: abre ou foca a aba da agenda
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    var targetUrl = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : 'agenda.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var c = clientList[i];
                if (c.url.indexOf('agenda.php') !== -1 && 'focus' in c) {
                    return c.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
