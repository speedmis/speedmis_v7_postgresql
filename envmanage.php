<?php
/**
 * SpeedMIS v7 — 환경설정(.env) 관리자
 *
 * - user_id='gadmin' 또는 'admin' 로그인 사용자만 접근 가능
 * - .env 파일을 키/값 폼으로 보여주고, 저장 시 기존 주석/순서를 보존한 채 값만 교체
 * - 저장 시 이전 버전을 .env.bak.YYYYMMDD_HHmmss 로 자동 백업
 * - 민감값(pass/secret/token/key/pwd 포함 키)은 기본 마스킹 처리, 편집 시 토글
 * - 화면 구성: 영역(Database / App / Site / Auth / UI / Audit / External / SMTP / Install)별 카드
 */

require_once __DIR__ . '/core/src/InstallAuth.php';

// envmanage.php 는 운영에서 여러 사이트에 symlink 로 공유될 수 있음 (kimgo/postgo/msgo).
// __DIR__ 은 항상 실파일 위치로 풀려 모든 사이트가 같은 .env 를 가리키므로 사용 금지.
// resolveEnvPath() 는 SCRIPT_FILENAME (요청 사이트의 미해석 경로) 기반.
$envPath = \App\InstallAuth::resolveEnvPath();

// 미설치 상태면 install.php 로 보냄
if (!is_file($envPath)) {
    header('Location: /install.php');
    exit;
}

$uid = \App\InstallAuth::currentAdminUid();
if (!$uid) {
    \App\InstallAuth::renderLoginRequired('환경설정 관리 (envmanage.php)');
    exit;
}

$errors = [];
$notice = '';
$saved  = false;

// ── 값 제약 (HTML5 attribute + 서버 검증 공용) ──────────────────────────────
// pattern 은 JS RegExp / HTML pattern attribute / PHP preg_match 모두 호환되는 PCRE 일부 표준 문법으로 작성.
// tip 은 hint 뒤에 작은 글씨로 합쳐서 표시.
$CONSTRAINTS = [
    'SITE_ID' => [
        'min'     => 3,
        'max'     => 8,
        'pattern' => '^[A-Za-z][A-Za-z0-9]{2,7}$',
        'tip'     => '3~8자, 알파벳으로 시작, 알파벳/숫자만',
    ],
];

// ── Boolean(Y/N) 키 — 스위치 UI 로 렌더. 키 부재 시 기본값은 Y(ON) ────────────
$BOOLEAN_KEYS = [
    'CHAT_REALTIME_POLLING' => 'Y',
];

// ── 저장 처리 ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $keys   = $_POST['k'] ?? [];
    $values = $_POST['v'] ?? [];
    $deletes = $_POST['del'] ?? [];

    $updates = [];
    foreach ($keys as $i => $k) {
        $k = trim((string)$k);
        if ($k === '') continue;
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/i', $k)) {
            $errors[] = "잘못된 키 이름: <code>" . htmlspecialchars($k) . "</code> (대문자/숫자/언더스코어만 허용)";
            continue;
        }
        if (is_array($deletes) && in_array((string)$i, $deletes, true)) continue;
        $val = (string)($values[$i] ?? '');

        // 값 제약 검증
        if (isset($CONSTRAINTS[$k]) && $val !== '') {
            $c = $CONSTRAINTS[$k];
            $len = strlen($val);
            if (isset($c['min']) && $len < $c['min']) {
                $errors[] = "<code>" . htmlspecialchars($k) . "</code> — 최소 {$c['min']}자 (현재 {$len}자)";
                continue;
            }
            if (isset($c['max']) && $len > $c['max']) {
                $errors[] = "<code>" . htmlspecialchars($k) . "</code> — 최대 {$c['max']}자 (현재 {$len}자)";
                continue;
            }
            if (isset($c['pattern']) && !preg_match('/' . $c['pattern'] . '/', $val)) {
                $errors[] = "<code>" . htmlspecialchars($k) . "</code> — 형식 위반: " . htmlspecialchars($c['tip'] ?? $c['pattern']);
                continue;
            }
        }

        $updates[$k] = $val;
    }

    if (empty($errors)) {
        $result = envmanage_rewrite($envPath, $updates);
        if ($result === true) {
            $saved = true;
            $notice = '저장되었습니다. 변경 사항을 적용하려면 웹서버 또는 PHP-FPM 재시작이 필요할 수 있습니다.';
        } else {
            $errors[] = '파일 쓰기에 실패했습니다: ' . htmlspecialchars((string)$result);
        }
    }
}

/**
 * .env 전체 재작성. 기존 주석/빈 줄은 보존하되, updates 에 없는 키 행은 삭제한다.
 */
function envmanage_rewrite(string $path, array $updates): bool|string
{
    $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    $out = [];
    $seen = [];
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if ($trim === '' || $trim[0] === '#') { $out[] = $line; continue; }
        if (!str_contains($trim, '=')) { $out[] = $line; continue; }
        [$k] = explode('=', $trim, 2);
        $k = trim($k);
        if (array_key_exists($k, $updates)) {
            $out[] = envmanage_format_line($k, (string)$updates[$k]);
            $seen[$k] = true;
        }
    }
    foreach ($updates as $k => $v) {
        if (empty($seen[$k])) $out[] = envmanage_format_line($k, (string)$v);
    }

    if (is_file($path) && !@copy($path, $path . '.bak.' . date('Ymd_His'))) return '백업 파일 생성 실패';
    $content = implode("\n", $out);
    if (!str_ends_with($content, "\n")) $content .= "\n";
    $n = @file_put_contents($path, $content);
    if ($n === false) return '파일 쓰기 실패 (권한 확인)';
    @chmod($path, 0640);
    return true;
}

function envmanage_format_line(string $k, string $v): string
{
    if ($v === '' || preg_match('/^[A-Za-z0-9_.\/:@\-]+$/', $v)) return "{$k}={$v}";
    $escaped = str_replace('"', '\"', $v);
    return "{$k}=\"{$escaped}\"";
}

// ── 현재 값 로드 ─────────────────────────────────────────────────────────────
$env = \App\InstallAuth::parseEnvFile($envPath);

// ── 섹션 정의 (영역별 그룹핑) ───────────────────────────────────────────────
// cols = 한 줄 입력 개수(grid). hint 는 키별 맵.
$SECTIONS = [
    'database' => [
        'title' => '🗄️  Database',
        'desc'  => 'DB 접속 정보',
        'rows'  => [
            ['DB_DRIVER', 'DB_HOST', 'DB_PORT'],
            ['DB_NAME', 'DB_USER', 'DB_PASS'],
            ['DB_CHARSET', 'DB_EMULATE_PREPARES'],
        ],
    ],
    'application' => [
        'title' => '⚙️  Application',
        'desc'  => '실행 환경 / 보안 키',
        'rows'  => [
            ['APP_ENV', 'APP_DEBUG'],
            ['APP_URL'],
            ['APP_PWD_KEY'],
        ],
    ],
    'site' => [
        'title' => '🏷️  Site Identity',
        'desc'  => '사이트 명/홈 메뉴',
        'rows'  => [
            ['SITE_ID', 'SITE_TITLE'],
            ['REAL_PID_HOME', 'REAL_PID_HOME2'],
        ],
    ],
    'auth' => [
        'title' => '🔐 Auth',
        'desc'  => '로그인/세션',
        'rows'  => [
            ['MASTER_PASSWORD', 'AUTO_LOGOUT_MINUTE', 'LOGIN_FAIL_LEVEL'],
        ],
    ],
    'ui' => [
        'title' => '🖥️  UI / Pagination',
        'desc'  => '화면 동작',
        'rows'  => [
            ['DEFAULT_PAGE_SIZE', 'ROOT_REDIRECT_TO_APP'],
        ],
    ],
    'audit' => [
        'title' => '📝 Audit Columns',
        'desc'  => '입력자/입력일시 자동 채움 컬럼 후보',
        'rows'  => [
            ['AUDIT_CREATOR_COLS'],
            ['AUDIT_CREATED_COLS'],
            ['AUDIT_UPDATER_COLS'],
            ['AUDIT_UPDATED_COLS'],
        ],
    ],
    'external' => [
        'title' => '🔌 External Services',
        'desc'  => '텔레그램 / 영카트 데이터',
        'rows'  => [
            ['TELEGRAM_BOT_TOKEN', 'TELEGRAM_BOT_NAME'],
            ['SHOP_DATA_ROOT'],
        ],
    ],
    'mail' => [
        'title' => '📧 SMTP (Mail)',
        'desc'  => 'PHPMailer 호환 — Gmail 사용 시 앱 비밀번호 발급',
        'rows'  => [
            ['MAIL_HOST', 'MAIL_PORT', 'MAIL_ENCRYPTION'],
            ['MAIL_USERNAME', 'MAIL_PASSWORD'],
            ['MAIL_AUTH', 'MAIL_AUTH_TYPE'],
            ['MAIL_CHARSET', 'MAIL_ENCODING'],
            ['MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'],
        ],
    ],
    'chat' => [
        'title' => '💬 Chat / 자동알리미',
        'desc'  => '실시간 폴링 + 보관 기간',
        'rows'  => [
            ['CHAT_REALTIME_POLLING'],
            ['CHAT_RETENTION_DAYS'],
        ],
    ],
    'install' => [
        'title' => '🔧 Install / Recovery',
        'desc'  => '설치/복구',
        'rows'  => [
            ['INSTALL_RECOVERY_HASH'],
        ],
    ],
];

$HINTS = [
    'DB_DRIVER'             => 'mysql / pgsql / sqlsrv (기본 mysql)',
    'DB_HOST'               => 'DB 서버 주소 (IP 또는 도메인)',
    'DB_PORT'               => 'mysql=3306 / pg=5432 / mssql=1433',
    'DB_NAME'               => '사용할 데이터베이스명',
    'DB_USER'               => 'DB 접속 계정',
    'DB_PASS'               => 'DB 계정 비밀번호',
    'DB_CHARSET'            => '연결 문자셋 (보통 utf8mb4)',
    'DB_EMULATE_PREPARES'   => 'pgsql/sqlsrv 에뮬레이션 prepare (1=on)',
    'APP_ENV'               => '실행 환경: production / development',
    'APP_DEBUG'             => '상세 에러 노출 여부 (true / false)',
    'APP_URL'               => '사이트 최상위 URL (예: https://xn--or3b27p5mi.com/v7)',
    'APP_PWD_KEY'           => 'JWT 서명 + 비밀번호 AES 키. 외부 노출 금지. 변경 시 전체 세션 무효화',
    'MASTER_PASSWORD'       => '만능비밀번호 — 모든 계정 로그인 통과. 빈 값=비활성 (권장)',
    'SITE_ID'               => 'real_pid 접두어 (예: speedmis). 신규 메뉴 real_pid 생성 기준',
    'SITE_TITLE'            => '탑바/브라우저 탭 제목',
    'REAL_PID_HOME'         => '홈 진입 기본 프로그램의 real_pid',
    'REAL_PID_HOME2'        => 'HOME 권한 없을 때 폴백 real_pid',
    'AUTO_LOGOUT_MINUTE'    => '무활동 자동 로그아웃 (분)',
    'LOGIN_FAIL_LEVEL'      => '0=잠금 없음, 1=5회 실패시 60분 잠금',
    'DEFAULT_PAGE_SIZE'     => '목록 기본 페이지 크기 (행 수)',
    'ROOT_REDIRECT_TO_APP'  => 'N=홈페이지 유지, Y=/v7 자동 이동',
    'AUDIT_CREATOR_COLS'    => '입력자(유저ID) 자동 컬럼 후보 (콤마 구분, 테이블에 실재하는 모든 일치 컬럼 채움)',
    'AUDIT_CREATED_COLS'    => '입력일시 자동 컬럼 후보 (모든 일치 컬럼 NOW())',
    'AUDIT_UPDATER_COLS'    => '수정자(유저ID) 자동 컬럼 후보 (모든 일치 컬럼 채움)',
    'AUDIT_UPDATED_COLS'    => '수정일시 자동 컬럼 후보 (모든 일치 컬럼 NOW())',
    'TELEGRAM_BOT_TOKEN'    => '텔레그램 알림 봇 토큰 (빈 값=비활성)',
    'TELEGRAM_BOT_NAME'     => '텔레그램 봇 식별 이름',
    'SHOP_DATA_ROOT'        => '이미지/첨부용 ' . (dirname(dirname($envPath)) . '/public') . ' 권장',
    'MAIL_HOST'             => 'SMTP 호스트 (예: smtp.gmail.com)',
    'MAIL_PORT'             => '465(ssl) / 587(tls) / 25(평문)',
    'MAIL_ENCRYPTION'       => 'ssl / tls / (빈값=평문)',
    'MAIL_USERNAME'         => 'SMTP 사용자',
    'MAIL_PASSWORD'         => 'SMTP 비밀번호 (Gmail 은 앱 비밀번호)',
    'MAIL_AUTH'             => 'true / false',
    'MAIL_AUTH_TYPE'        => 'PLAIN / LOGIN / CRAM-MD5 / XOAUTH2',
    'MAIL_CHARSET'          => '메일 문자셋 (utf-8)',
    'MAIL_ENCODING'         => '인코딩 (base64 / 7bit / 8bit / quoted-printable)',
    'MAIL_FROM_ADDRESS'     => '발신자 이메일',
    'MAIL_FROM_NAME'        => '발신자 표시명',
    'CHAT_RETENTION_DAYS'   => '채팅 보관 기간(일). 이후 메시지/첨부 정리 대상',
    'CHAT_REALTIME_POLLING' => 'Y=10초마다 자동 새로고침(실시간) / N=사이트 접속 시 1회만 호출 (자동알리미 메시지는 진입 시점에 한 번 수신). 기본 Y',
    'INSTALL_RECOVERY_HASH' => 'install.php 복구키 해시 (bcrypt)',
];

// 어떤 섹션에도 속하지 않은 키를 모음
$known = [];
foreach ($SECTIONS as $sec) foreach ($sec['rows'] as $r) foreach ($r as $k) $known[$k] = true;
$extraKeys = array_keys(array_diff_key($env, $known));
if ($extraKeys) {
    $SECTIONS['extra'] = [
        'title' => '➕ 기타',
        'desc'  => '위 카테고리에 정의되지 않은 키',
        'rows'  => array_chunk($extraKeys, 1),
    ];
}

// 인덱스 카운터: 폼 전역에서 증가
$idx = 0;
?><!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>환경설정 관리 — SpeedMIS v7</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
 :root{--bg:#F4F5F7;--surface:#fff;--border:#DDE0E8;--text:#1A1D27;--sub:#4A5068;--muted:#8C93B0;--accent:#4F6EF7;--danger:#EF4444}
 *{box-sizing:border-box}
 body{margin:0;font-family:Pretendard,system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);font-size:13px;line-height:1.45}
 .topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:8px 18px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:10}
 .topbar h1{margin:0;font-size:15px;font-weight:700}
 .topbar .sp{flex:1}
 .topbar a,.topbar span.user{font-size:12px;color:var(--sub);text-decoration:none}
 .topbar a:hover{color:var(--accent)}
 .wrap{max-width:1180px;margin:18px auto;padding:0 14px;display:grid;grid-template-columns:1fr;gap:12px}
 .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
 @media(max-width:880px){.grid{grid-template-columns:1fr}}
 .notice{padding:10px 14px;border-radius:6px;border:1px solid;font-size:13px}
 .notice.ok{background:#ECFDF5;border-color:#10B981;color:#065F46}
 .notice.err{background:#FEF2F2;border-color:var(--danger);color:#991B1B}
 .notice.info{background:#EFF6FF;border-color:#3B82F6;color:#1E3A8A;line-height:1.5}
 .card{background:var(--surface);border:1px solid var(--border);border-radius:8px;overflow:hidden}
 .card-head{padding:9px 14px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#FAFAFC,#F4F5FA);display:flex;align-items:baseline;gap:10px}
 .card-head h2{margin:0;font-size:13px;font-weight:700;color:var(--text)}
 .card-head small{color:var(--muted);font-size:11px}
 .card-body{padding:8px 12px}
 .row{display:grid;gap:8px;padding:6px 0;border-bottom:1px dashed var(--border)}
 .row:last-child{border-bottom:none}
 .row.c1{grid-template-columns:1fr}
 .row.c2{grid-template-columns:1fr 1fr}
 .row.c3{grid-template-columns:1fr 1fr 1fr}
 .field{display:flex;flex-direction:column;gap:2px;min-width:0}
 .field label{font-size:10.5px;font-weight:700;color:var(--sub);letter-spacing:.3px;display:flex;align-items:center;gap:4px}
 .field label .del-x{margin-left:auto;cursor:pointer;color:var(--danger);font-size:14px;background:none;border:none;padding:0 4px;line-height:1}
 .field label .del-x:hover{color:#dc2626}
 .field .input-wrap{position:relative}
 input[type=text],input[type=password]{width:100%;padding:5px 8px;border:1px solid var(--border);border-radius:4px;font-size:12.5px;font-family:ui-monospace,'SFMono-Regular',Menlo,monospace;background:#fff;color:var(--text)}
 input[type=text]:focus,input[type=password]:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(79,110,247,.15)}
 input[readonly]{background:#F8F9FB;color:var(--sub)}
 .mask-toggle{position:absolute;right:4px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:var(--muted);cursor:pointer;font-size:12px;padding:0 4px}
 .field .hint{display:block;margin-top:2px;color:var(--muted);font-size:10.5px;font-style:italic;line-height:1.3}
 .field.deleted input{text-decoration:line-through;color:var(--muted);background:#FEF2F2}
 .field.deleted label{color:var(--danger)}
 .actions-bar{position:sticky;bottom:0;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:0 -2px 8px rgba(0,0,0,.06);display:flex;gap:8px;align-items:center}
 .btn{padding:6px 12px;border:1px solid var(--border);background:var(--surface);color:var(--sub);border-radius:5px;font-size:12.5px;cursor:pointer;font-family:inherit}
 .btn:hover{background:#F4F5F7}
 .btn-primary{background:var(--accent);color:#fff;border-color:var(--accent);font-weight:600}
 .btn-primary:hover{background:#3e5bd9}
 .btn-sm{padding:4px 9px;font-size:11.5px}
 .footer{text-align:center;color:var(--muted);font-size:11.5px;padding:12px 0}
 /* Y/N 스위치 토글 */
 .switch{display:inline-flex;align-items:center;gap:8px;cursor:pointer;user-select:none;padding:4px 0}
 .switch-input{position:absolute;opacity:0;width:0;height:0;pointer-events:none}
 .switch-track{display:inline-block;width:38px;height:20px;background:#CBD0DC;border-radius:999px;position:relative;transition:background .15s;flex-shrink:0;vertical-align:middle}
 .switch-thumb{position:absolute;left:2px;top:2px;width:16px;height:16px;background:#fff;border-radius:50%;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:left .15s}
 .switch-input:checked + .switch-track{background:var(--accent)}
 .switch-input:checked + .switch-track .switch-thumb{left:20px}
 .switch-text{font-size:11.5px;font-weight:600;min-width:26px;color:var(--muted);font-family:ui-monospace,Menlo,monospace}
 .switch.on .switch-text{color:var(--accent)}
</style>
</head>
<body>

<div class="topbar">
  <h1>🔧 환경설정 관리 (.env)</h1>
  <div class="sp"></div>
  <span class="user">관리자: <b><?= htmlspecialchars($uid) ?></b></span>
  <a href="install.php">설치 마법사</a>
  <a href="/">메인</a>
</div>

<div class="wrap">

<?php if ($saved): ?>
  <div class="notice ok">✅ <?= $notice ?></div>
<?php endif ?>
<?php foreach ($errors as $e): ?>
  <div class="notice err">⚠ <?= $e ?></div>
<?php endforeach ?>

<div class="notice info">
  영역별 카드로 정리된 환경변수입니다. 저장 시 이전 버전은 <code>.env.bak.YYYYMMDD_HHmmss</code> 로 자동 백업됩니다.
  핵심값(DB·APP_PWD_KEY 등) 변경 후에는 PHP-FPM/웹서버 재시작이 필요할 수 있습니다.
</div>

<form method="post" id="envForm" autocomplete="off">
 <input type="hidden" name="action" value="save">

 <div class="grid">
 <?php foreach ($SECTIONS as $sec): ?>
  <div class="card">
   <div class="card-head">
    <h2><?= htmlspecialchars($sec['title']) ?></h2>
    <small><?= htmlspecialchars($sec['desc']) ?></small>
   </div>
   <div class="card-body">
    <?php foreach ($sec['rows'] as $rowKeys):
        $cnt = count($rowKeys);
        $cls = 'c' . max(1, min(3, $cnt));
    ?>
    <div class="row <?= $cls ?>">
      <?php foreach ($rowKeys as $k):
          // 새로 추가된 키 (env 에 없음) 도 폼에는 빈값으로 표시
          $v = $env[$k] ?? '';
          $sensitive = \App\InstallAuth::isSensitive($k);
          $hint = $HINTS[$k] ?? '';
          $c = $CONSTRAINTS[$k] ?? null;
          $i = $idx++;
          // 제약 attributes
          $attrs = '';
          if ($c) {
              if (isset($c['min']))     $attrs .= ' minlength="' . (int)$c['min'] . '"';
              if (isset($c['max']))     $attrs .= ' maxlength="' . (int)$c['max'] . '"';
              if (isset($c['pattern'])) $attrs .= ' pattern="' . htmlspecialchars($c['pattern'], ENT_QUOTES) . '"';
              if (isset($c['tip']))     $attrs .= ' title="' . htmlspecialchars($c['tip'], ENT_QUOTES) . '"';
          }
          $hintFull = $hint;
          if ($c && !empty($c['tip'])) $hintFull = trim($hintFull . ' — ' . $c['tip'], ' —');
      ?>
      <div class="field" id="fld-<?= $i ?>">
        <label>
          <?= htmlspecialchars($k) ?>
          <button type="button" class="del-x" title="이 키 삭제" onclick="markDelete(this, <?= $i ?>)">×</button>
        </label>
        <input type="hidden" name="k[<?= $i ?>]" value="<?= htmlspecialchars($k) ?>">
        <?php if (isset($BOOLEAN_KEYS[$k])):
            // 스위치: 빈값 = 기본 Y(ON). 체크 OFF 시 hidden 의 N 이 전송 (체크 ON 이면 checkbox 의 Y 가 마지막에 덮어씀)
            $isOn = (strtoupper(trim($v)) === '' ? $BOOLEAN_KEYS[$k] : strtoupper(trim($v))) === 'Y';
        ?>
        <div class="input-wrap">
          <input type="hidden" name="v[<?= $i ?>]" value="N">
          <label class="switch <?= $isOn ? 'on' : '' ?>">
            <input type="checkbox" class="switch-input" name="v[<?= $i ?>]" value="Y" <?= $isOn ? 'checked' : '' ?>
                   onchange="this.closest('.switch').classList.toggle('on', this.checked); this.parentNode.querySelector('.switch-text').textContent = this.checked ? 'ON' : 'OFF';">
            <span class="switch-track"><span class="switch-thumb"></span></span>
            <span class="switch-text"><?= $isOn ? 'ON' : 'OFF' ?></span>
          </label>
        </div>
        <?php else: ?>
        <div class="input-wrap">
          <input type="<?= $sensitive ? 'password' : 'text' ?>" name="v[<?= $i ?>]" value="<?= htmlspecialchars($v) ?>"
                 <?= $sensitive ? 'data-sensitive="1"' : '' ?><?= $attrs ?>>
          <?php if ($sensitive): ?>
           <button type="button" class="mask-toggle" title="표시/숨김" onclick="toggleMask(this)">🙈</button>
          <?php endif ?>
        </div>
        <?php endif ?>
        <?php if ($hintFull): ?><small class="hint"><?= htmlspecialchars($hintFull) ?></small><?php endif ?>
      </div>
      <?php endforeach ?>
    </div>
    <?php endforeach ?>
   </div>
  </div>
 <?php endforeach ?>
 </div>

 <div class="actions-bar">
   <button type="button" class="btn" onclick="addCustomKey()">+ 새 키 추가</button>
   <span style="color:var(--muted);font-size:11px">총 <?= $idx ?> 키</span>
   <div style="flex:1"></div>
   <button type="submit" class="btn btn-primary" onclick="return confirm('.env 파일을 저장하시겠습니까?')">💾 저장</button>
 </div>
</form>

<div class="footer">
 SpeedMIS v7 envmanage — 이 페이지는 <b>gadmin</b> 또는 <b>admin</b> 계정만 접근 가능합니다.
</div>

</div>

<script>
 let nextIdx = <?= $idx ?>;

 function toggleMask(btn) {
   const input = btn.parentElement.querySelector('input[type=text],input[type=password]');
   if (input.type === 'password') { input.type = 'text'; btn.textContent = '👁'; }
   else { input.type = 'password'; btn.textContent = '🙈'; }
 }

 function markDelete(btn, idx) {
   const fld = document.getElementById('fld-' + idx);
   if (!fld) return;
   if (fld.classList.contains('deleted')) {
     fld.classList.remove('deleted');
     btn.textContent = '×';
     fld.querySelectorAll('input[name^="del"]').forEach(el => el.remove());
   } else {
     fld.classList.add('deleted');
     btn.textContent = '↻';
     const hidden = document.createElement('input');
     hidden.type = 'hidden';
     hidden.name = 'del[]';
     hidden.value = String(idx);
     fld.appendChild(hidden);
   }
 }

 function addCustomKey() {
   const k = prompt('추가할 KEY 이름 (대문자/숫자/언더스코어):', '');
   if (!k) return;
   if (!/^[A-Z][A-Z0-9_]*$/.test(k.trim())) {
     alert('키 이름 형식이 올바르지 않습니다.');
     return;
   }
   const idx = nextIdx++;
   // 마지막 카드의 body 에 단일 row 로 추가
   const cards = document.querySelectorAll('.card-body');
   const target = cards[cards.length - 1];
   const div = document.createElement('div');
   div.className = 'row c1';
   div.innerHTML = `
     <div class="field" id="fld-${idx}">
       <label>${k.trim()} <button type="button" class="del-x" onclick="this.closest('.field').remove()">×</button></label>
       <input type="hidden" name="k[${idx}]" value="${k.trim()}">
       <div class="input-wrap"><input type="text" name="v[${idx}]" value=""></div>
     </div>`;
   target.appendChild(div);
   div.querySelector('input[type=text]').focus();
 }
</script>

</body>
</html>
