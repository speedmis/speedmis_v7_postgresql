<?php
/**
 * SpeedMIS v7 (PostgreSQL 배포판) — Install Wizard
 *
 * 워드프레스식 최초 구동 설치:
 *   1) PostgreSQL 접속정보 입력 → 연결 테스트 → DB 생성(없으면)
 *   2) 초기 데이터(speedmis_db) 자동 적재
 *        · 로컬 db/speedmis_db.sql(.gz) 우선 → 없으면 GitHub Public 레포에서 자동 다운로드
 *        · gapm.kr 실시간 연결 없음
 *   3) SITE_ID 를 접속 URL 에서 자동 생성 (소문자/숫자 3~8자)
 *   4) .env 자동 생성 (MASTER_PASSWORD=4321 → 마스킹된 비밀번호 대신 만능비번으로 로그인)
 *   5) 완료
 *
 * 설치 후에는 user_id='gadmin' 또는 'admin' 로그인 사용자만 접근 가능.
 *
 * ⚠ 이 레포(speedmis_v7_postgresql)는 MariaDB / MSSQL 배포판과 별개입니다.
 *    설치는 PostgreSQL 전용입니다.
 */

require_once __DIR__ . '/core/src/InstallAuth.php';
require_once __DIR__ . '/core/src/SiteId.php';

use App\InstallAuth;
use App\SiteId;

/** DB 번들 기본 다운로드 위치 (Public 레포 raw). 로컬 db/ 가 있으면 그쪽 우선 */
const DB_BUNDLE_URL_DEFAULT = 'https://raw.githubusercontent.com/speedmis/speedmis_v7_postgresql/main/db/speedmis_db.sql.gz';

$envPath     = InstallAuth::resolveEnvPath();      // 표준 배포(심링크 없음)에서는 __DIR__/.env
$isInstalled = file_exists($envPath);
$isForce     = $isInstalled && isset($_GET['force']);   // force=1 + 이미 설치 = DB 재적재 모드 (.env 보존)
$envData     = $isForce ? InstallAuth::parseEnvFile($envPath) : [];

// 이미 설치된 경우: admin/gadmin 인증 (또는 복구키) 필요
if ($isInstalled) {
    $authUid = InstallAuth::requireAccess('설치 마법사 (install.php)');
}

if ($isInstalled && !$isForce) {
    $uidLabel = (($authUid ?? '') === '__recovery__') ? '복구 키 인증' : ('관리자(' . htmlspecialchars($authUid ?? '') . ') 로그인');
    $distroLabel = 'PostgreSQL';
    $distroRepo  = 'speedmis_v7_postgresql';
    $bundleLabel = 'speedmis_db';
    ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SpeedMIS v7 (<?= $distroLabel ?>) — Admin</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Pretendard', -apple-system, sans-serif; background:#f4f5f7; color:#1a1d27; min-height: 100vh; padding: 40px 20px; }
  .wrap { width: 580px; max-width: 100%; margin: 0 auto; }
  .header-card { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.08); padding:28px 32px; margin-bottom:16px; text-align:center; }
  .header-card .ok-icon { font-size:42px; color:#16a34a; line-height:1; margin-bottom:8px; }
  .header-card h1 { font-size:20px; font-weight:700; margin-bottom:4px; }
  .header-card .sub { color:#8c93b0; font-size:13px; }
  .group { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.05); padding:6px; margin-bottom:12px; }
  .group .group-title { padding:10px 14px 6px; font-size:11px; font-weight:700; color:#8c93b0; letter-spacing:.5px; text-transform: uppercase; }
  .row { display:flex; align-items:center; gap:14px; padding:14px 14px; border-radius:8px; text-decoration:none; color:inherit; transition: background 0.12s; }
  .row:hover { background:#f8f9fb; text-decoration:none; }
  .row .icon { font-size:22px; width:28px; flex-shrink:0; text-align:center; }
  .row .body { flex:1; }
  .row .title { font-size:14px; font-weight:600; color:#1a1d27; margin-bottom:3px; }
  .row .desc { font-size:12px; color:#8c93b0; line-height:1.55; }
  .row .arrow { color:#c8ccda; font-size:18px; flex-shrink:0; }
  .row.danger { background:#fef2f2; }
  .row.danger:hover { background:#fee2e2; }
  .row.danger .title { color:#b91c1c; }
  .row.danger .desc { color:#dc2626; opacity:0.85; }
  .footer { text-align:center; color:#8c93b0; font-size:11px; padding:14px 0; }
  code { font-family: ui-monospace, monospace; background:#f0f1f5; padding:1px 5px; border-radius:3px; font-size:0.92em; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header-card">
    <div class="ok-icon">✓</div>
    <h1>SpeedMIS v7 (<?= $distroLabel ?>) 설치 완료</h1>
    <p class="sub"><?= $uidLabel ?> 확인됨</p>
  </div>

  <div class="group">
    <div class="group-title">사이트</div>
    <a href="/" class="row">
      <span class="icon">🏠</span>
      <div class="body">
        <div class="title">메인으로 이동</div>
        <div class="desc">설치된 사이트의 메인 페이지로 이동합니다.</div>
      </div>
      <span class="arrow">›</span>
    </a>
  </div>

  <div class="group">
    <div class="group-title">설정·관리</div>
    <a href="envmanage.php" class="row">
      <span class="icon">⚙️</span>
      <div class="body">
        <div class="title">환경설정 (.env) 관리</div>
        <div class="desc">DB 접속 정보·사이트 제목·마스터 비밀번호 등 환경변수를 직접 편집합니다.</div>
      </div>
      <span class="arrow">›</span>
    </a>
    <a href="update.php" class="row">
      <span class="icon">🔄</span>
      <div class="body">
        <div class="title">파일 업데이트</div>
        <div class="desc">GitHub <code><?= $distroRepo ?></code> 의 최신 소스 파일을 받아 변경/추가분만 덮어쓰기 합니다. <strong>DB 는 절대 건드리지 않습니다.</strong></div>
      </div>
      <span class="arrow">›</span>
    </a>
  </div>

  <div class="group">
    <div class="group-title">위험 — 데이터 손실</div>
    <a href="?force=1" class="row danger" onclick="return confirm('정말 DB 를 재적재할까요?\n기존에 입력한 모든 데이터가 사라집니다.\n(.env 는 보존됩니다)');">
      <span class="icon">⚠️</span>
      <div class="body">
        <div class="title">DB 재적재 (force)</div>
        <div class="desc">DB 의 모든 테이블을 초기 상태(<code><?= $bundleLabel ?></code> 번들)로 되돌립니다. <strong>기존 입력 데이터가 모두 사라집니다.</strong><br><code>.env</code> (APP_PWD_KEY · MASTER_PASSWORD 등) 는 <strong>변경되지 않습니다</strong>.</div>
      </div>
      <span class="arrow">›</span>
    </a>
  </div>

  <p class="footer">보안을 위해 운영 전환 후 install.php 삭제 권장</p>
</div>
</body>
</html>
<?php
    exit;
}

$step    = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors  = [];
$log     = [];

// ── SQL 번들 로더 ────────────────────────────────────────────────────────────
function load_bundle_sql(string $baseDir, string $url, array &$log): ?string
{
    foreach (['db/speedmis_db.sql.gz', 'db/speedmis_db.sql'] as $rel) {
        $p = $baseDir . '/' . $rel;
        if (is_file($p)) {
            $raw = @file_get_contents($p);
            if ($raw !== false && str_ends_with($rel, '.gz')) $raw = @gzdecode($raw);
            if (is_string($raw) && $raw !== '') {
                $log[] = "로컬 초기데이터 사용: {$rel} (" . round(strlen($raw) / 1024) . " KB)";
                return $raw;
            }
        }
    }
    // 원격 다운로드 (Public 레포 raw)
    $ctx = stream_context_create(['http' => [
        'header'  => "User-Agent: SpeedMIS-Installer\r\n",
        'timeout' => 180,
    ]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return null;
    if (str_ends_with($url, '.gz')) {
        $d = @gzdecode($data);
        if ($d !== false) $data = $d;
    }
    $log[] = "원격 초기데이터 다운로드: " . round(strlen($data) / 1024) . " KB";
    return $data;
}

// ── force 모드: 기존 객체 전부 DROP (CASCADE) ────────────────────────────
function drop_all_objects_pgsql(PDO $pdo, array &$log): void
{
    $tCount = 0; $vCount = 0; $sCount = 0;
    try {
        // 1) View (CASCADE — 의존성 자동 정리)
        $views = $pdo->query("SELECT viewname FROM pg_views WHERE schemaname='public'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($views as $v) {
            $safe = '"' . str_replace('"', '""', $v) . '"';
            try { $pdo->exec("DROP VIEW IF EXISTS public.{$safe} CASCADE"); $vCount++; } catch (\Throwable) {}
        }
        // 2) Table (CASCADE)
        $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $safe = '"' . str_replace('"', '""', $t) . '"';
            try { $pdo->exec("DROP TABLE IF EXISTS public.{$safe} CASCADE"); $tCount++; } catch (\Throwable) {}
        }
        // 3) Sequence (테이블에 종속되지 않은 잔여 시퀀스)
        $seqs = $pdo->query("SELECT sequence_name FROM information_schema.sequences WHERE sequence_schema='public'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($seqs as $s) {
            $safe = '"' . str_replace('"', '""', $s) . '"';
            try { $pdo->exec("DROP SEQUENCE IF EXISTS public.{$safe} CASCADE"); $sCount++; } catch (\Throwable) {}
        }
    } catch (\Throwable $e) {
        $log[] = "객체 정리 단계 경고: " . $e->getMessage();
    }
    $log[] = "기존 객체 정리: 테이블 {$tCount}개, 뷰 {$vCount}개, 시퀀스 {$sCount}개 DROP (force 재적재)";
}

// ── PostgreSQL 덤프 실행 ─────────────────────────────────────────────────────
// pg_dump 결과는 dollar-quoted 함수($$...$$) 와 COPY 블록을 포함할 수 있어 단순 ';' split 불가.
// pdo_pgsql 은 PDO::exec() 에 다문장 SQL 을 그대로 전달하면 libpq 가 한꺼번에 처리한다.
// 트랜잭션으로 감싸 실패 시 전체 롤백 — 부분 적재 상태를 남기지 않음.
function exec_pg_dump(PDO $pdo, string $sql): array
{
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $pdo->commit();
        return ['ok' => true, 'message' => 'success'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            try { $pdo->rollBack(); } catch (\Throwable) {}
        }
        return ['ok' => false, 'message' => substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 400)];
    }
}

// ── STEP 2: 연결 테스트 → DB 생성 → 초기데이터 적재 → .env 작성 ───────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost    = trim($_POST['db_host'] ?? '');
    $dbPort    = trim($_POST['db_port'] ?? '5432');
    $dbName    = trim($_POST['db_name'] ?? 'speedmis_db');
    $dbUser    = trim($_POST['db_user'] ?? '');
    $dbPass    = $_POST['db_pass'] ?? '';
    $siteTitle = trim($_POST['site_title'] ?? 'SpeedMIS v7');
    $appUrl    = trim($_POST['app_url'] ?? ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));

    if (!$dbHost) $errors[] = 'DB 호스트를 입력하세요.';
    if (!$dbUser) $errors[] = 'DB 사용자를 입력하세요.';
    if (!$dbName) $errors[] = 'DB 이름을 입력하세요.';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) $errors[] = 'DB 이름은 영문/숫자/밑줄만 가능합니다.';
    if (!extension_loaded('pdo_pgsql')) {
        $errors[] = 'PHP pdo_pgsql 확장이 설치되어 있지 않습니다. (php-pgsql 패키지 필요)';
    }

    // 1) 기본 'postgres' DB 에 접속해 대상 DB 존재 확인 + 없으면 생성
    //    (공유 호스팅은 CREATE DATABASE 권한이 없을 수 있어 graceful fallback)
    if (empty($errors)) {
        try {
            $dsnAdmin = "pgsql:host={$dbHost};port={$dbPort};dbname=postgres;connect_timeout=5";
            $pdo = new PDO($dsnAdmin, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $exists = $pdo->prepare("SELECT 1 FROM pg_database WHERE datname=?");
            $exists->execute([$dbName]);
            if ($exists->fetchColumn()) {
                $log[] = "기존 데이터베이스 사용: {$dbName}";
            } else {
                try {
                    // dbName 은 위에서 정규식 검증됨 → 안전. PG identifier 큰따옴표 인용.
                    $safeName = '"' . str_replace('"', '""', $dbName) . '"';
                    $pdo->exec("CREATE DATABASE {$safeName} WITH ENCODING 'UTF8' TEMPLATE template0");
                    $log[] = "데이터베이스 생성: {$dbName}";
                } catch (PDOException $e2) {
                    $log[] = "⚠ DB 자동 생성 권한이 없어 보입니다 (공유호스팅 등 흔한 케이스).";
                    $log[] = "  → 호스팅 관리자 페이지에서 '{$dbName}' DB 를 미리 만들고 다시 시도하세요.";
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'DB 서버 연결 실패: ' . $e->getMessage();
        }
    }

    // 2) 대상 DB 로 재접속, 기설치 여부 확인 (force 모드는 검사 스킵하고 기존 객체 DROP)
    if (empty($errors)) {
        try {
            $dsnDb = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};connect_timeout=5";
            $pdo = new PDO($dsnDb, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if (!$isForce) {
                $has = $pdo->query("SELECT to_regclass('public.mis_menus')::text")->fetchColumn();
                if ($has) {
                    $errors[] = "'{$dbName}' 에 이미 mis_menus 테이블이 존재합니다. 빈 DB를 쓰거나 DB 이름을 바꾸세요.";
                }
            } else {
                // force 모드: 기존 public 스키마의 모든 객체 DROP (pg_dump 번들에 DROP 없음 → 명시 필요)
                drop_all_objects_pgsql($pdo, $log);
            }
        } catch (PDOException $e) {
            $errors[] = 'DB 선택 실패: ' . $e->getMessage();
        }
    }

    // 3) 초기 데이터 적재
    if (empty($errors)) {
        $bundle = load_bundle_sql(__DIR__, DB_BUNDLE_URL_DEFAULT, $log);
        if ($bundle === null || $bundle === '') {
            $errors[] = '초기 데이터(speedmis_db)를 불러오지 못했습니다. 인터넷 연결 또는 db/speedmis_db.sql 파일을 확인하세요.';
        } else {
            $res = exec_pg_dump($pdo, $bundle);
            if (!$res['ok']) {
                $errors[] = '초기 데이터 적재 실패: ' . $res['message'];
            } else {
                // 적재 후 sanity check — pg_dump 가 search_path 를 비우므로 schema 명시 필수
                try {
                    $n = (int)$pdo->query("SELECT count(*) FROM public.mis_menus")->fetchColumn();
                    $log[] = "초기 데이터 적재 완료 (mis_menus {$n} 행)";
                } catch (\Throwable $e) {
                    $errors[] = '적재는 끝났으나 public.mis_menus 가 검증되지 않습니다: ' . $e->getMessage();
                }
            }
        }
    }

    // 4) .env 작성 (+ SITE_ID 자동생성) — force 모드면 .env 손대지 않고 통과
    if (empty($errors) && $isForce) {
        $log[] = ".env 보존 (force 모드 — APP_PWD_KEY / MASTER_PASSWORD / SITE_ID 등 모두 유지).";
        $siteDir = dirname($envPath);
        foreach (['uploadFiles', 'uploadFiles/_temp', 'logs', 'logs/cache'] as $d) {
            $dir = $siteDir . '/' . $d;
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
        }
        $step = 3;
    }
    if (empty($errors) && !$isForce) {
        $pwdKey = bin2hex(random_bytes(32));

        // 접속 URL → SITE_ID
        $host    = parse_url($appUrl, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $derived = SiteId::fromHost($host);
        if ($derived !== null) {
            $siteId = $derived;
            $siteAuto = 'done';            // 도메인에서 정상 도출
        } else {
            $siteId = SiteId::provisional($host);
            $siteAuto = 'pending';         // IP/localhost → 도메인 접속 시 자동 갱신
        }

        $titleEsc = str_replace('"', '\"', $siteTitle);
        $env = <<<ENV
# SpeedMIS v7 (PostgreSQL 배포판) — install.php 가 자동 생성
DB_DRIVER=pgsql
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}
DB_CHARSET=utf8
DB_EMULATE_PREPARES=0

APP_ENV=production
APP_DEBUG=false
APP_URL={$appUrl}
APP_PWD_KEY={$pwdKey}

SITE_ID={$siteId}
SITE_ID_AUTO={$siteAuto}
SITE_TITLE="{$titleEsc}"
REAL_PID_HOME=speedmis000314
REAL_PID_HOME2=

MASTER_PASSWORD=4321
AUTO_LOGOUT_MINUTE=30
LOGIN_FAIL_LEVEL=1

DEFAULT_PAGE_SIZE=25
ROOT_REDIRECT_TO_APP=N

AUDIT_CREATOR_COLS=wdater,writer,created_by,create_by,regist_id
AUDIT_CREATED_COLS=wdate,created_at,create_at,write_date,regist_dt
AUDIT_UPDATER_COLS=lastupdater,updater,updated_by,modify_by
AUDIT_UPDATED_COLS=lastupdate,updated_at,modify_date,update_dt

TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_NAME=
SHOP_DATA_ROOT=

CHAT_RETENTION_DAYS=30
CHAT_REALTIME_POLLING=Y

DB_BUNDLE_URL=
INSTALL_RECOVERY_HASH=
ENV;
        if (file_put_contents($envPath, $env) === false) {
            $errors[] = '.env 작성 실패 — 디렉토리 쓰기 권한을 확인하세요.';
        } else {
            $log[] = ".env 생성 완료 (SITE_ID={$siteId}, MASTER_PASSWORD=4321)";
            // 디렉토리 준비
            $siteDir = dirname($envPath);
            foreach (['uploadFiles', 'uploadFiles/_temp', 'logs', 'logs/cache'] as $d) {
                $dir = $siteDir . '/' . $d;
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
            }
            $step = 3;
        }
    }

    if (!empty($errors)) $step = 1;
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SpeedMIS v7 (PostgreSQL) Install</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Pretendard', -apple-system, sans-serif; background: #f4f5f7; color: #1a1d27; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); width: 500px; max-width: 95vw; padding: 40px; }
  h1 { font-size: 22px; margin-bottom: 6px; }
  .sub { color: #8c93b0; font-size: 14px; margin-bottom: 24px; }
  .tag { display:inline-block; font-size:11px; font-weight:700; color:#fff; background:#336791; border-radius:4px; padding:2px 8px; margin-bottom:14px; letter-spacing:.5px; }
  .step-bar { display: flex; gap: 8px; margin-bottom: 24px; }
  .step-dot { flex: 1; height: 4px; border-radius: 2px; background: #dde0e8; }
  .step-dot.active { background: #4f6ef7; }
  .step-dot.done { background: #22c55e; }
  label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #4a5068; }
  input[type=text], input[type=password], input[type=number] {
    width: 100%; height: 38px; border: 1px solid #dde0e8; border-radius: 6px;
    padding: 0 12px; font-size: 14px; outline: none; transition: border 0.15s;
  }
  input:focus { border-color: #4f6ef7; }
  .row { margin-bottom: 16px; }
  .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
  .btn { width: 100%; height: 42px; border: 0; border-radius: 6px; font-size: 15px; font-weight: 600; background: #4f6ef7; color: #fff; cursor: pointer; transition: background 0.15s; }
  .btn:hover { background: #3b5de7; }
  .err { background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
  .ok { background: #f0fdf4; border: 1px solid #86efac; color: #16a34a; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
  .log { background: #f8f9fb; border: 1px solid #dde0e8; border-radius: 6px; padding: 12px; font-size: 13px; margin-bottom: 16px; line-height: 1.8; }
  .hint { font-size: 12px; color: #8c93b0; margin-top: 4px; }
  .done-icon { font-size: 48px; text-align: center; margin-bottom: 16px; }
  a { color: #4f6ef7; text-decoration: none; }
  a:hover { text-decoration: underline; }

  /* 설치 버튼 spinner */
  .btn .btn-spinner { display:none; width:14px; height:14px; border:2px solid rgba(255,255,255,0.45); border-top-color:#FFF; border-radius:50%; margin-right:8px; vertical-align:-3px; animation: install-spin 0.7s linear infinite; }
  .btn.is-loading .btn-spinner { display:inline-block; }
  .btn.is-loading { background:#3b5de7; cursor:wait; }
  .btn.is-loading .btn-label::after { content:" 중..."; }
  @keyframes install-spin { to { transform: rotate(360deg); } }

  /* 전체 화면 오버레이 */
  #install-overlay { display:none; position:fixed; inset:0; background:rgba(15,17,23,0.55); z-index:9999; align-items:center; justify-content:center; padding:20px; }
  .install-overlay__card { background:#fff; border-radius:14px; padding:36px 42px; text-align:center; max-width:440px; width:100%; box-shadow:0 18px 60px rgba(0,0,0,0.25); }
  .install-overlay__spinner { width:48px; height:48px; border:4px solid #E5E8EB; border-top-color:#4F6EF7; border-radius:50%; margin:0 auto 18px; animation: install-spin 0.9s linear infinite; }
  .install-overlay__card h2 { font-size:18px; font-weight:700; margin-bottom:10px; color:#191F28; }
  .install-overlay__card p  { font-size:14px; line-height:1.7; color:#4E5968; }

</style>
</head>
<body>
<div class="card">

  <div class="step-bar">
    <div class="step-dot <?= $step >= 2 ? 'done' : ($step === 1 ? 'active' : '') ?>"></div>
    <div class="step-dot <?= $step >= 3 ? 'done' : ($step === 2 ? 'active' : '') ?>"></div>
  </div>

<?php if ($step === 1): // ── DB 접속정보 ── ?>
  <span class="tag"<?= $isForce ? ' style="background:#dc2626"' : '' ?>><?= $isForce ? 'FORCE — DB 재적재' : 'PostgreSQL EDITION' ?></span>
  <h1><?= $isForce ? 'DB 재적재 (force)' : 'SpeedMIS v7 설치' ?></h1>
  <?php if ($isForce): ?>
    <p class="sub" style="color:#b91c1c;font-weight:500"><strong>⚠ 위험:</strong> 기존 public 스키마의 모든 객체(테이블·뷰·시퀀스)가 DROP CASCADE 되고 초기 상태(speedmis_db 번들)로 되돌아갑니다. <code>.env</code> 는 보존됩니다.</p>
  <?php else: ?>
    <p class="sub">PostgreSQL 접속 정보를 입력하면, 초기 데이터를 자동으로 받아 설치합니다.</p>
  <?php endif; ?>

  <?php foreach ($errors as $e): ?><div class="err"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  <?php if (!empty($log)): ?><div class="log"><?php foreach ($log as $l): ?><?= htmlspecialchars($l) ?><br><?php endforeach; ?></div><?php endif; ?>

  <?php
    $f_dbHost = $_POST['db_host']    ?? ($isForce ? ($envData['DB_HOST']    ?? '127.0.0.1') : '127.0.0.1');
    $f_dbPort = $_POST['db_port']    ?? ($isForce ? ($envData['DB_PORT']    ?? '5432')      : '5432');
    $f_dbName = $_POST['db_name']    ?? ($isForce ? ($envData['DB_NAME']    ?? 'speedmis_db') : 'speedmis_db');
    $f_dbUser = $_POST['db_user']    ?? ($isForce ? ($envData['DB_USER']    ?? 'postgres')  : 'postgres');
    $f_dbPass = $_POST['db_pass']    ?? ($isForce ? ($envData['DB_PASS']    ?? '')          : '');
    $f_title  = $_POST['site_title'] ?? ($isForce ? ($envData['SITE_TITLE'] ?? 'SpeedMIS v7') : 'SpeedMIS v7');
    $f_url    = $_POST['app_url']    ?? ($isForce ? ($envData['APP_URL']    ?? '')          : ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
  ?>
  <form method="post" <?= $isForce ? 'action="?force=1"' : '' ?>>
    <input type="hidden" name="step" value="2">
    <div class="row">
      <label>DB 호스트 (PostgreSQL)</label>
      <input type="text" name="db_host" value="<?= htmlspecialchars($f_dbHost) ?>" placeholder="127.0.0.1 또는 호스트명" required<?= $isForce ? ' readonly style="background:#f8f9fb;cursor:not-allowed"' : '' ?>>
    </div>
    <div class="row2">
      <div>
        <label>DB 포트</label>
        <input type="number" name="db_port" value="<?= htmlspecialchars($f_dbPort) ?>" placeholder="5432"<?= $isForce ? ' readonly style="background:#f8f9fb;cursor:not-allowed"' : '' ?>>
      </div>
      <div>
        <label>DB 이름</label>
        <input type="text" name="db_name" value="<?= htmlspecialchars($f_dbName) ?>" placeholder="speedmis_db" required<?= $isForce ? ' readonly style="background:#f8f9fb;cursor:not-allowed"' : '' ?>>
      </div>
    </div>
    <div class="row2">
      <div>
        <label>DB 사용자</label>
        <input type="text" name="db_user" value="<?= htmlspecialchars($f_dbUser) ?>" required<?= $isForce ? ' readonly style="background:#f8f9fb;cursor:not-allowed"' : '' ?>>
      </div>
      <div>
        <label>DB 비밀번호</label>
        <input type="password" name="db_pass" value="<?= htmlspecialchars($f_dbPass) ?>"<?= $isForce ? ' placeholder="(.env 의 기존값 사용)"' : '' ?>>
      </div>
    </div>
    <?php if (!$isForce): ?>
    <div class="row">
      <label>사이트 제목</label>
      <input type="text" name="site_title" value="<?= htmlspecialchars($f_title) ?>">
    </div>
    <div class="row">
      <label>사이트 URL</label>
      <input type="text" name="app_url" value="<?= htmlspecialchars($f_url) ?>" placeholder="http://example.com">
      <div class="hint">이 주소에서 <b>SITE_ID</b> 가 자동 생성됩니다 (소문자/숫자 3~8자). IP면 임시값 → 나중에 도메인 접속 시 자동 갱신.</div>
    </div>
    <div class="hint" style="margin-bottom:20px">DB가 없으면 자동 생성하고, 초기 데이터(speedmis_db)를 받아 설치합니다.</div>
    <?php else: ?>
    <input type="hidden" name="site_title" value="<?= htmlspecialchars($f_title) ?>">
    <input type="hidden" name="app_url" value="<?= htmlspecialchars($f_url) ?>">
    <div class="hint" style="margin-bottom:20px;color:#b91c1c">계속하면 public 스키마의 모든 테이블·뷰·시퀀스가 DROP CASCADE 되고 초기 speedmis_db 번들로 재적재됩니다.</div>
    <?php endif; ?>
    <button type="submit" class="btn" id="install-submit-btn"<?= $isForce ? ' style="background:#dc2626"' : '' ?>>
      <span class="btn-spinner" aria-hidden="true"></span>
      <span class="btn-label"><?= $isForce ? 'DB 재적재 시작' : '연결 &amp; 자동 설치' ?></span>
    </button>
  </form>

  <!-- 설치 진행 중 오버레이 (submit 시 표시) -->
  <div id="install-overlay" aria-hidden="true">
    <div class="install-overlay__card">
      <div class="install-overlay__spinner"></div>
      <h2>설치 진행 중...</h2>
      <p>
        DB 자동 생성 + 초기데이터(약 1MB) 다운로드 + 116 테이블 적재.<br>
        평균 <b>20~60초</b> 소요됩니다. 창을 닫지 마세요.
      </p>
    </div>
  </div>

  <script>
    (function () {
      var form = document.querySelector('form[method="post"]');
      if (!form) return;
      form.addEventListener('submit', function () {
        var btn = document.getElementById('install-submit-btn');
        if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }
        var ov = document.getElementById('install-overlay');
        if (ov) ov.style.display = 'flex';
      });
    })();
  </script>

<?php elseif ($step === 3): // ── 완료 ── ?>
  <div class="done-icon">&#10004;</div>
  <h1 style="text-align:center"><?= $isForce ? 'DB 재적재 완료!' : '설치 완료!' ?></h1>
  <p class="sub" style="text-align:center">
    <?php if ($isForce): ?>
      SpeedMIS v7 (PostgreSQL) DB 가 초기 상태로 재적재되었습니다. <strong>.env 는 그대로 보존</strong>되었습니다.
    <?php else: ?>
      SpeedMIS v7 (PostgreSQL) 이 성공적으로 설치되었습니다.
    <?php endif; ?>
  </p>
  <?php if (!empty($log)): ?><div class="log"><?php foreach ($log as $l): ?><?= htmlspecialchars($l) ?><br><?php endforeach; ?></div><?php endif; ?>
  <div class="ok">
    <?php if ($isForce): ?>
      기존 <strong>.env</strong> 의 APP_PWD_KEY · MASTER_PASSWORD · SITE_ID 가 그대로 유지되었으므로 기존 로그인 정보로 접속 가능합니다.<br>
      단, DB 의 사용자 데이터(직원·거래처·주문 등)는 모두 사라졌습니다.
    <?php else: ?>
      로그인은 <strong>gadmin</strong> / 비번 <strong>4321</strong> 로 로그인하세요.<br>
      운영 전환 시 <strong>.env 의 MASTER_PASSWORD 를 반드시 변경/비활성</strong> 하세요.<br>
      보안을 위해 <strong>install.php 삭제</strong>를 권장합니다.
    <?php endif; ?>
  </div>
  <a href="/" style="display:block;text-align:center;margin-top:20px;font-size:15px;font-weight:600"><?= $isForce ? '메인으로 이동' : '로그인 페이지로 이동' ?> &rarr;</a>

<?php endif; ?>

</div>
</body>
</html>
