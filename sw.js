/**
 * SpeedMIS v7 — Service Worker
 *
 * 전략:
 *  - HTML/JS API 응답: network-first (오프라인 대비 fallback)
 *  - /public/build/* (Vite 빌드 산출물): cache-first + 영구 캐시 (해시드 파일)
 *  - 정적 자원 (favicon/css/이미지): stale-while-revalidate
 *  - 설치 시 핵심 셸만 사전 캐시
 */

const VERSION    = 'mis-v7-sw-2026-05-08-04';
const CACHE_CORE = `mis-core-${VERSION}`;
const CACHE_RUN  = `mis-run-${VERSION}`;

const CORE_ASSETS = [
  '/v7/',
  '/manifest.json',
  '/pwa/icon-192.png',
  '/pwa/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_CORE).then(cache => cache.addAll(CORE_ASSETS).catch(() => {}))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(
      keys.filter(k => !k.endsWith(VERSION)).map(k => caches.delete(k))
    );
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // API 호출(/v7/api.php) 은 항상 네트워크 우선 — 캐시는 오프라인 fallback 용도만
  if (url.pathname.endsWith('/api.php')) {
    event.respondWith(networkFirst(req));
    return;
  }

  // Vite 빌드 (해시 파일명 → 영구 캐시)
  if (url.pathname.includes('/public/build/') || url.pathname.includes('/build/assets/')) {
    event.respondWith(cacheFirst(req));
    return;
  }

  // 기타 정적 (css/이미지/폰트 등)
  if (/\.(css|js|png|jpg|jpeg|webp|gif|svg|ico|woff2?|ttf)$/i.test(url.pathname)) {
    event.respondWith(staleWhileRevalidate(req));
    return;
  }

  // HTML/SPA 진입 — 네트워크 우선, 실패 시 /v7/ 셸로 fallback
  if (req.mode === 'navigate') {
    event.respondWith(navigationHandler(req));
    return;
  }
});

async function cacheFirst(req) {
  const cache = await caches.open(CACHE_RUN);
  const hit = await cache.match(req);
  if (hit) return hit;
  try {
    const res = await fetch(req);
    if (res.ok) cache.put(req, res.clone());
    return res;
  } catch (e) {
    return new Response('offline', { status: 503 });
  }
}

async function networkFirst(req) {
  const cache = await caches.open(CACHE_RUN);
  try {
    const res = await fetch(req);
    if (res.ok) cache.put(req, res.clone());
    return res;
  } catch (e) {
    const hit = await cache.match(req);
    return hit || new Response(JSON.stringify({success:false, message:'offline'}),
      { status: 503, headers: { 'Content-Type': 'application/json' } });
  }
}

async function staleWhileRevalidate(req) {
  const cache = await caches.open(CACHE_RUN);
  const cached = await cache.match(req);
  const fetching = fetch(req).then(res => {
    if (res.ok) cache.put(req, res.clone());
    return res;
  }).catch(() => cached);
  return cached || fetching;
}

async function navigationHandler(req) {
  try {
    const res = await fetch(req);
    return res;
  } catch (e) {
    const cache = await caches.open(CACHE_CORE);
    const shell = await cache.match('/v7/');
    return shell || new Response('offline', { status: 503 });
  }
}

// 클라이언트에서 캐시 강제 비우기 트리거
self.addEventListener('message', async (event) => {
  if (event.data === 'mis:clearCache') {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => caches.delete(k)));
    event.ports?.[0]?.postMessage('cleared');
  }
});

// ── Push 알림 수신 ─────────────────────────────────────────────────
self.addEventListener('push', (event) => {
  let payload = {};
  try { payload = event.data ? event.data.json() : {}; } catch(e) {}
  const title = payload.title || '부자톡 Admin';
  const body  = payload.body  || '';
  const url   = payload.url   || '/v7/';
  const tag   = payload.tag   || undefined;
  event.waitUntil(self.registration.showNotification(title, {
    body,
    icon : '/pwa/icon-192.png',
    badge: '/pwa/icon-192.png',
    data : { url, ...(payload.data || {}) },
    tag,
    renotify: !!tag,
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data?.url || '/v7/';
  event.waitUntil((async () => {
    const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    // 이미 열린 창이 있으면 거기로 focus + 메시지로 라우팅
    for (const c of allClients) {
      if (c.url.includes('/v7/')) {
        c.postMessage({ type: 'mis:notificationClick', url: targetUrl });
        return c.focus();
      }
    }
    return self.clients.openWindow(targetUrl);
  })());
});
