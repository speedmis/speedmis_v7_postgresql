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

// 이미 설치된 경우: admin/gadmin 인증 (또는 복구키) 필요
if ($isInstalled) {
    $authUid = InstallAuth::requireAccess('설치 마법사 (install.php)');
}

if ($isInstalled && !isset($_GET['force'])) {
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="utf-8"><title>SpeedMIS (PostgreSQL)</title></head><body style="font-family:sans-serif;text-align:center;padding:80px">';
    echo '<h2>SpeedMIS v7 (PostgreSQL) 은 이미 설치되어 있습니다.</h2>';
    $uidLabel = (($authUid ?? '') === '__recovery__') ? '복구 키 인증' : ('관리자(' . htmlspecialchars($authUid ?? '') . ') 로그인');
    echo '<p style="color:#888;font-size:13px;margin-bottom:24px">' . $uidLabel . ' 확인됨</p>';
    echo '<p style="margin:20px 0"><a href="/">메인으로 이동</a></p>';
    echo '<p><a href="envmanage.php" style="color:#4f6ef7">환경설정(.env) 관리</a></p>';
    echo '<p style="color:#aaa;font-size:12px;margin-top:24px"><a href="?force=1" style="color:#dc2626">다시 설치 (force) — 기존 .env 덮어씀</a></p>';
    echo '</body></html>';
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

    // 2) 대상 DB 로 재접속, 기설치 여부 확인
    if (empty($errors)) {
        try {
            $dsnDb = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};connect_timeout=5";
            $pdo = new PDO($dsnDb, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $has = $pdo->query("SELECT to_regclass('public.mis_menus')::text")->fetchColumn();
            if ($has) {
                $errors[] = "'{$dbName}' 에 이미 mis_menus 테이블이 존재합니다. 빈 DB를 쓰거나 DB 이름을 바꾸세요.";
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

    // 4) .env 작성 (+ SITE_ID 자동생성)
    if (empty($errors)) {
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
  <span class="tag">PostgreSQL EDITION</span>
  <h1>SpeedMIS v7 설치</h1>
  <p class="sub">PostgreSQL 접속 정보를 입력하면, 초기 데이터를 자동으로 받아 설치합니다.</p>

  <?php foreach ($errors as $e): ?><div class="err"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  <?php if (!empty($log)): ?><div class="log"><?php foreach ($log as $l): ?><?= htmlspecialchars($l) ?><br><?php endforeach; ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="step" value="2">
    <div class="row">
      <label>DB 호스트 (PostgreSQL)</label>
      <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') ?>" placeholder="127.0.0.1 또는 호스트명" required>
    </div>
    <div class="row2">
      <div>
        <label>DB 포트</label>
        <input type="number" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '5432') ?>" placeholder="5432">
      </div>
      <div>
        <label>DB 이름</label>
        <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'speedmis_db') ?>" placeholder="speedmis_db" required>
      </div>
    </div>
    <div class="row2">
      <div>
        <label>DB 사용자</label>
        <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'postgres') ?>" required>
      </div>
      <div>
        <label>DB 비밀번호</label>
        <input type="password" name="db_pass" value="">
      </div>
    </div>
    <div class="row">
      <label>사이트 제목</label>
      <input type="text" name="site_title" value="<?= htmlspecialchars($_POST['site_title'] ?? 'SpeedMIS v7') ?>">
    </div>
    <div class="row">
      <label>사이트 URL</label>
      <input type="text" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))) ?>" placeholder="http://example.com">
      <div class="hint">이 주소에서 <b>SITE_ID</b> 가 자동 생성됩니다 (소문자/숫자 3~8자). IP면 임시값 → 나중에 도메인 접속 시 자동 갱신.</div>
    </div>
    <div class="hint" style="margin-bottom:20px">DB가 없으면 자동 생성하고, 초기 데이터(speedmis_db)를 받아 설치합니다.</div>
    <button type="submit" class="btn" id="install-submit-btn">
      <span class="btn-spinner" aria-hidden="true"></span>
      <span class="btn-label">연결 &amp; 자동 설치</span>
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
  <h1 style="text-align:center">설치 완료!</h1>
  <p class="sub" style="text-align:center">SpeedMIS v7 (PostgreSQL) 이 성공적으로 설치되었습니다.</p>
  <?php if (!empty($log)): ?><div class="log"><?php foreach ($log as $l): ?><?= htmlspecialchars($l) ?><br><?php endforeach; ?></div><?php endif; ?>
  <div class="ok">
    로그인은 <strong>gadmin</strong> / 비번 <strong>4321</strong> 로 로그인하세요.<br>
    운영 전환 시 <strong>.env 의 MASTER_PASSWORD 를 반드시 변경/비활성</strong> 하세요.<br>
    보안을 위해 <strong>install.php 삭제</strong>를 권장합니다.
  </div>
  <a href="/" style="display:block;text-align:center;margin-top:20px;font-size:15px;font-weight:600">로그인 페이지로 이동 &rarr;</a>

<?php endif; ?>

</div>
</body>
</html>
