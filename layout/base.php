<?php
/**
 * HTML 쉘 — React SPA 진입점
 * window.__INITIAL_DATA__ 와 window.__APP_CONFIG__ 주입
 */

/** @var array $initialData */
/** @var array $appConfig */
$initialData = $initialData ?? [];
$appConfig   = $appConfig   ?? [];

$siteTitle = htmlspecialchars($_ENV['SITE_TITLE'] ?? 'SpeedMIS', ENT_QUOTES);
$appUrl    = rtrim($_ENV['APP_URL'] ?? '', '/');
// 배포 base path — index.php 에서 계산해 $appConfig 에 담아옴 (예: '/v7' 또는 '')
$bp        = $appConfig['basePath'] ?? '';

// Vite manifest 읽기 (production)
function getViteAssets(string $bp = ''): array
{
    $manifestPath = PUBLIC_PATH . '/build/.vite/manifest.json';
    if (!file_exists($manifestPath)) {
        $manifestPath = PUBLIC_PATH . '/build/manifest.json';
    }
    if (!file_exists($manifestPath)) return ['js' => '', 'css' => ''];

    $manifest = json_decode(file_get_contents($manifestPath), true) ?? [];
    $entry    = $manifest['src/main.jsx'] ?? $manifest['index.html'] ?? null;
    if (!$entry) return ['js' => '', 'css' => ''];

    $js  = $bp . '/public/build/' . ($entry['file']    ?? '');
    $css = !empty($entry['css'][0]) ? $bp . '/public/build/' . $entry['css'][0] : '';
    return compact('js', 'css');
}

// APP_ENV 미정의 = production 가정 (배포판은 기본 prod 모드 — Vite dev 서버 localhost:5173 자산 link 회피)
$isProd  = ($_ENV['APP_ENV'] ?? 'production') !== 'development';
$assets  = $isProd ? getViteAssets($bp) : null;
$vitePort = 5173;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $siteTitle ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="<?= $bp ?>/favicon.ico">
    <link rel="apple-touch-icon" href="/pwa/icon-192.png">
    <link rel="apple-touch-icon" sizes="192x192" href="/pwa/icon-192.png">
    <link rel="apple-touch-icon" sizes="512x512" href="/pwa/icon-512.png">
    <meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0E1117" media="(prefers-color-scheme: dark)">

    <!-- PWA manifest -->
    <link rel="manifest" href="<?= $bp ?>/manifest.json">

    <!-- iOS PWA -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= $siteTitle ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="<?= $siteTitle ?>">

    <!-- FOUC 방지: 페이지 렌더 전에 테마 적용 (인라인 필수) -->
    <script>
        (function(){
            var t = localStorage.getItem('mis_theme');
            if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        })();
    </script>

    <!-- PWA: service worker 등록 + 설치 프롬프트 캡처 -->
    <script>
        (function(){
            // beforeinstallprompt 를 가로채 SettingsButton 에서 사용 가능하게 보관
            window.__pwaInstallPrompt = null;
            window.addEventListener('beforeinstallprompt', function(e){
                e.preventDefault();
                window.__pwaInstallPrompt = e;
                window.dispatchEvent(new CustomEvent('mis:pwaInstallable'));
            });
            window.addEventListener('appinstalled', function(){
                window.__pwaInstallPrompt = null;
                window.dispatchEvent(new CustomEvent('mis:pwaInstalled'));
            });
            // SW 등록 — HTTPS 또는 localhost 에서만 동작
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function(){
                    navigator.serviceWorker.register('<?= $bp ?>/sw.js', { scope: '<?= $bp ?: '/' ?>/' })
                        .catch(function(){});
                });
            }
        })();
    </script>

    <!-- 폰트 -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">

    <!-- 디자인 시스템 — 파일 mtime 으로 cache-bust (배포 직후 폰 캐시도 즉시 갱신) -->
    <?php
    $cssBust = function(string $rel) use ($bp): string {
        $abs = __DIR__ . '/..' . $rel;
        $mt  = @filemtime($abs);
        return $bp . $rel . ($mt ? '?v=' . $mt : '');
    };
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssBust('/public/css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($cssBust('/public/css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($cssBust('/public/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($cssBust('/public/css/mobile.css')) ?>">

    <?php if ($isProd && $assets && $assets['css']): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($assets['css']) ?>">
    <?php endif; ?>

    <style>
        #loading-screen {
            position: fixed; inset: 0; display: flex;
            align-items: center; justify-content: center;
            background: var(--color-bg, #0F1117); z-index: 9999;
        }
        #loading-screen.hidden { display: none; }
        #loading-screen__inner { text-align: center; color: var(--color-text-3, #5C6389); }
        #loading-screen__icon  { font-size: 28px; margin-bottom: 10px; }
        #loading-screen__label { font-size: 13px; font-family: var(--font-sans, sans-serif); }
    </style>
</head>
<body>

<div id="loading-screen">
    <div id="loading-screen__inner">
        <div id="loading-screen__icon">⚡</div>
        <div id="loading-screen__label"><?= $siteTitle ?></div>
    </div>
</div>

<div id="root"></div>

<script>
window.__APP_CONFIG__ = <?= json_encode($appConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
window.__INITIAL_DATA__ = <?= json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>

<?php if ($isProd && $assets && $assets['js']): ?>
    <script type="module" src="<?= htmlspecialchars($assets['js']) ?>"></script>
<?php else: ?>
    <!-- @vitejs/plugin-react preamble (커스텀 백엔드 필수) -->
    <script type="module">
        import RefreshRuntime from 'http://localhost:<?= $vitePort ?>/@react-refresh'
        RefreshRuntime.injectIntoGlobalHook(window)
        window.$RefreshReg$ = () => {}
        window.$RefreshSig$ = () => (type) => type
        window.__vite_plugin_react_preamble_installed__ = true
    </script>
    <!-- Vite HMR (개발) -->
    <script type="module" src="http://localhost:<?= $vitePort ?>/@vite/client"></script>
    <script type="module" src="http://localhost:<?= $vitePort ?>/src/main.jsx"></script>
<?php endif; ?>

</body>
</html>
