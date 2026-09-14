/**
 * booth/sw.js — Booth PWA service worker.
 * Caches only the static shell (manifest + icon). Every page and every
 * API call goes to the network; biometric actions must NEVER run offline.
 */
const SHELL = [
    './manifest.webmanifest',
    './icons/icon.svg'
];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open('booth-shell-v1').then((c) => c.addAll(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== 'booth-shell-v1').map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    // Only same-origin GETs for static shell assets; everything else = network.
    if (e.request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (!SHELL.some((p) => url.pathname.endsWith(p.replace('./', '/')))) return;

    e.respondWith(
        caches.match(e.request).then((hit) => hit || fetch(e.request))
    );
});
