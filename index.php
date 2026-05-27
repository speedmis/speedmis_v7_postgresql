<?php
/**
 * SpeedMIS v7 — SPA 프론트 컨트롤러
 * 정적 파일은 web.config/Apache 가 먼저 처리 → 여기 오는 건 HTML 껍데기만
 */

define('BASE_PATH', __DIR__);

// ─── .env 없으면 설치 마법사로 자동 안내 (워드프레스 패턴) ──────────────────
// 사용자가 git clone / ZIP 압축해제만 한 직후 / 로 접근하면 SPA 로딩에서 멈춤.
// → install.php 로 리다이렉트해 DB 정보 입력 한 번으로 설치 시작.
// (운영본은 .env 가 항상 있어서 이 조건 안 만족 → 평소처럼 동작)
if (!is_file(BASE_PATH . '/.env') && is_file(BASE_PATH . '/install.php')) {
    header('Location: /install.php', true, 302);
    exit;
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// 환경변수 로드
$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

// ─── 단축 URL 리다이렉트 (?s=XXXXXXX) ───────────────────────────────────────
if (isset($_GET['s']) && preg_match('/^[A-Za-z0-9]{4,10}$/', $_GET['s'])) {
    try {
        $pdo  = \App\Config\Database::getInstance();
        $stmt = $pdo->prepare('SELECT long_url FROM mis_urls WHERE short_code = ? LIMIT 1');
        $stmt->execute([trim($_GET['s'])]);
        $row  = $stmt->fetch();
        if ($row) {
            header('Location: ' . $row['long_url'], true, 302);
            exit;
        }
    } catch (\Throwable) {}
    // 코드 없으면 업무시스템 메인으로 이동 (랜딩페이지가 아니라 앱 경로)
    header('Location: /v7/', true, 302);
    exit;
}

// 배포 경로 base path 자동 감지 — /v7/index.php → '/v7' / 루트 → ''
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

// ─── SSR: 초기 데이터 구성 ──────────────────────────────────────────────────
$initialData = [];
$appConfig   = [
    'siteTitle'       => $_ENV['SITE_TITLE']          ?? 'SpeedMIS',
    'basePath'        => $basePath,
    'apiUrl'          => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath . '/api.php',
    'autoLogoutMin'   => (int)($_ENV['AUTO_LOGOUT_MINUTE'] ?? 30),
    'defaultPageSize' => DEFAULT_PAGE_SIZE,
    'maxPageSize'     => MAX_PAGE_SIZE,
    'appEnv'          => $_ENV['APP_ENV']              ?? 'production',
    // 자동알리미(채팅) 실시간 폴링 사용여부 — N 이면 진입 시 1회만 호출
    'chatRealtimePolling' => strtoupper((string)($_ENV['CHAT_REALTIME_POLLING'] ?? 'Y')) !== 'N',
    // envmanage.php 에서 한 번이라도 .env 를 저장했는지 (저장 시 .env.bak.YYYYMMDD_HHmmss 자동 생성됨)
    // false 면 gadmin 첫 진입 시 React 측에서 "환경설정 관리에서 확인하세요" 안내 카드 노출
    'envTouched'      => count(glob(BASE_PATH . '/.env.bak.*') ?: []) > 0,
];

// 쿠키에서 access_token 검증 → 사용자 정보 주입 (SPA 초기 렌더 최적화)
$cookieToken = $_COOKIE['access_token'] ?? '';
if ($cookieToken) {
    try {
        $secret  = $_ENV['APP_PWD_KEY'] ?? 'secret';
        $payload = JWT::decode($cookieToken, new Key($secret, JWT_ALGO));
        if (($payload->type ?? '') === 'access') {
            $appConfig['user'] = [
                'uid'           => $payload->uid           ?? '',
                'name'          => $payload->name          ?? '',
                'is_admin'      => $payload->is_admin      ?? 'N',
                'is_dev'        => $payload->is_dev        ?? 'N',
                'position_code' => $payload->position_code ?? '',
                'station_idx'   => $payload->station_idx   ?? null,
                'station_name'  => '',
                'theme'         => 'light',
            ];
            // DB 최신값으로 is_dev / station_name / theme 보강 — me API 응답과 동등한 user 객체 구성
            try {
                $pdo = \App\Config\Database::getInstance();
                $uid = $payload->uid ?? '';
                $st  = $pdo->prepare('SELECT 1 FROM mis_group_members WHERE user_id = ? AND group_idx = 83 LIMIT 1');
                $st->execute([$uid]);
                $appConfig['user']['is_dev'] = $st->fetchColumn() ? 'Y' : 'N';

                $st2 = $pdo->prepare(
                    'SELECT u.station_idx, s.station_name, u.theme
                       FROM mis_users u LEFT JOIN mis_stations s ON s.idx = u.station_idx
                      WHERE u.user_id = ? LIMIT 1'
                );
                $st2->execute([$uid]);
                if ($row = $st2->fetch(\PDO::FETCH_ASSOC)) {
                    $appConfig['user']['station_idx']  = (int)($row['station_idx'] ?? 0);
                    $appConfig['user']['station_name'] = (string)($row['station_name'] ?? '');
                    $appConfig['user']['theme']        = (string)($row['theme'] ?: 'light');
                }
            } catch (\Throwable) {}
        }
    } catch (\Throwable) {
        // 만료/무효 토큰 → 로그인 화면으로
    }
}

// REAL_PID_HOME: 기본 진입 메뉴 설정 (읽기권한 없으면 REAL_PID_HOME2 로 폴백)
$realPidHome  = $_ENV['REAL_PID_HOME']  ?? '';
$realPidHome2 = $_ENV['REAL_PID_HOME2'] ?? '';

/**
 * 특정 real_pid 메뉴에 현재 사용자가 읽기 권한이 있는지 확인
 * - payload=null (비로그인) 은 접근 없음 처리
 * - is_admin=Y → 항상 허용
 * - auth_code 비어있음 → 공개 메뉴 (허용)
 * - 그 외 → mis_menu_auth / mis_group_members 기반 권한 체크
 */
$hasMenuAccess = function(PDO $pdo, array $menu, ?object $payload): bool {
    if (!$payload) return false;
    if (($payload->is_admin ?? '') === 'Y') return true;
    if (($menu['auth_code'] ?? '') === '') return true;
    $uid = $payload->uid ?? '';
    if ($uid === '') return false;
    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM mis_menu_auth
              WHERE user_id = ? AND real_pid = ? AND authority_level > 0 LIMIT 1'
        );
        $stmt->execute([$uid, $menu['real_pid'] ?? '']);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable) {
        return false;
    }
};

try {
    $pdo  = \App\Config\Database::getInstance();
    $menuStmt = $pdo->prepare(
        'SELECT idx, real_pid, autogubun, up_real_pid, auth_code FROM mis_menus WHERE real_pid = ? AND useflag = \'1\' LIMIT 1'
    );

    $homeMenu = null;
    foreach ([$realPidHome, $realPidHome2] as $candidate) {
        if ($candidate === '') continue;
        $menuStmt->execute([$candidate]);
        $row = $menuStmt->fetch();
        if ($row && $hasMenuAccess($pdo, $row, $payload ?? null)) {
            $homeMenu = $row;
            break;
        }
    }

    if ($homeMenu) {
        $appConfig['homeGubun'] = (int)$homeMenu['idx'];
        // 최상위 real_pid: autogubun 앞 2자리와 일치하는 메뉴 찾기
        $topCode = substr($homeMenu['autogubun'], 0, 2);
        $stmt2 = $pdo->prepare(
            'SELECT real_pid FROM mis_menus WHERE autogubun = ? AND idx <> 1 AND useflag = \'1\' LIMIT 1'
        );
        $stmt2->execute([$topCode]);
        $topRow = $stmt2->fetch();
        $appConfig['homeTopRealPid'] = $topRow['real_pid'] ?? '';
    }
} catch (\Throwable) {}

// CSRF 쿠키 발급 (없으면 새로 생성)
if (empty($_COOKIE['csrf_token'])) {
    $csrfToken = bin2hex(random_bytes(32));
    setcookie('csrf_token', $csrfToken, [
        'expires'  => time() + 3600,
        'path'     => '/',
        'httponly' => false,
        'samesite' => 'Strict',
    ]);
}

// ─── HTML 출력 ─────────────────────────────────────────────────────────────
require_once BASE_PATH . '/layout/base.php';
