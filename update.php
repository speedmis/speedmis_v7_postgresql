<?php
/**
 * SpeedMIS v7 — File Update Wizard (파일 갱신 마법사)
 *
 * 설치된 사이트의 소스 파일을 GitHub 최신본으로 갱신.
 *   - DB 는 건드리지 않음 (install.php 와 분리)
 *   - 보존: .env / .env.bak.* / logs/ / uploadFiles/ / uploads/ / db/ /
 *           public/data/ / public/build/data/ / update.php(self) / .gitignore 의 ignore 항목
 *   - .env 의 DB_DRIVER 로 3 distro 중 자기 레포 자동 인식
 *
 * 동작:
 *   step 1) GitHub 의 main tree 가져와서 local 과 git-blob SHA 비교 → 변경/추가 파일 리스트
 *   step 2) 사용자가 "적용" → raw URL 로 한 파일씩 받아 덮어쓰기
 *
 * 보안: install.php 와 동일하게 InstallAuth::requireAccess (gadmin/admin 로그인 또는 복구키).
 */

require_once __DIR__ . '/core/src/InstallAuth.php';

use App\InstallAuth;

// ── 보존 목록: 절대 덮어쓰면 안 되는 경로 (prefix 매칭) ────────────────────
const PRESERVE_PREFIXES = [
    '.env',                // .env, .env.example 도 매칭됨 → 아래에서 .env.example 은 예외 처리
    'logs/',
    'uploadFiles/',
    'uploads/',
    'db/',                 // 초기 데이터 번들 (install 단계에서 이미 적재)
    'public/data/',
    'public/build/data/',
    'fix_upload_perms.php',
    'update.php',          // 실행 중인 자기 자신
];
// 예외: 위 prefix 에 걸려도 갱신해야 하는 파일
const PRESERVE_EXCEPT = [
    '.env.example',        // 갱신 OK (.env 와 다름)
];

// ── DB driver → distro repo 매핑 ─────────────────────────────────────────
const REPO_BY_DRIVER = [
    'mysql'  => 'speedmis/speedmis_v7_mariadb',
    'pgsql'  => 'speedmis/speedmis_v7_postgresql',
    'sqlsrv' => 'speedmis/speedmis_v7_mssql',
];
const BRANCH = 'main';

// ── 1. 진입 점검 ─────────────────────────────────────────────────────────
$envPath = InstallAuth::resolveEnvPath();
if (!file_exists($envPath)) {
    header('Location: install.php');
    exit;
}

$authUid = InstallAuth::requireAccess('업데이트 (update.php)');

$envData   = InstallAuth::parseEnvFile($envPath);
$dbDriver  = strtolower(trim($envData['DB_DRIVER'] ?? 'mysql'));
$repo      = REPO_BY_DRIVER[$dbDriver] ?? null;
if (!$repo) {
    http_response_code(500);
    exit('지원되지 않는 DB_DRIVER: ' . htmlspecialchars($dbDriver));
}

$baseDir = dirname($envPath);
$step    = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors  = [];
$log     = [];
$diff    = [];           // [ ['path'=>..., 'type'=>'new|changed', 'size'=>...], ... ]
$applied = [];           // 적용된 파일 목록
$applyFail = [];         // 적용 실패 목록

// ── 2. 유틸 ──────────────────────────────────────────────────────────────
function http_get(string $url, ?string $accept = null, int $timeout = 60): ?string
{
    $headers = "User-Agent: SpeedMIS-Updater\r\n";
    if ($accept) $headers .= "Accept: $accept\r\n";
    $ctx = stream_context_create(['http' => [
        'header'  => $headers,
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return null;
    // HTTP 상태 코드 확인 (404/403 도 텍스트 반환됨)
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int)$m[1];
    }
    if ($status >= 400) return null;
    return $data;
}

function git_blob_sha(string $filePath): string
{
    $content = @file_get_contents($filePath);
    if ($content === false) return '';
    return sha1("blob " . strlen($content) . "\0" . $content);
}

function path_is_preserved(string $rel): bool
{
    if (in_array($rel, PRESERVE_EXCEPT, true)) return false;
    foreach (PRESERVE_PREFIXES as $p) {
        if ($p === $rel || str_starts_with($rel, $p)) return true;
    }
    return false;
}

function format_size(int $n): string
{
    if ($n < 1024) return $n . ' B';
    if ($n < 1024 * 1024) return round($n / 1024, 1) . ' KB';
    return round($n / 1024 / 1024, 2) . ' MB';
}

// ── 3. step 2/3: 원격 tree 가져오기 + diff 계산 ────────────────────────────
$treeFetched = false;
$latestSha   = '';

if ($step === 2 || $step === 3) {
    $treeUrl = "https://api.github.com/repos/{$repo}/git/trees/" . BRANCH . "?recursive=1";
    $log[] = "원격 트리 조회: {$treeUrl}";
    $raw   = http_get($treeUrl, 'application/vnd.github+json', 90);
    if ($raw === null) {
        $errors[] = '원격 트리 조회 실패 — 네트워크 또는 GitHub API 율제한(60/시간) 가능.';
    } else {
        $obj = json_decode($raw, true);
        if (!is_array($obj) || empty($obj['tree'])) {
            $errors[] = '원격 트리 응답 형식 오류.';
        } else {
            $treeFetched = true;
            $latestSha   = $obj['sha'] ?? '';
            if (!empty($obj['truncated'])) {
                $errors[] = '경고: 트리가 truncated 됨 (파일 수 과다). 일부 누락 가능.';
            }
            foreach ($obj['tree'] as $entry) {
                if (($entry['type'] ?? '') !== 'blob') continue;
                $rel = $entry['path'] ?? '';
                if ($rel === '' || path_is_preserved($rel)) continue;

                $local = $baseDir . '/' . $rel;
                if (!file_exists($local)) {
                    $diff[] = [
                        'path' => $rel,
                        'type' => 'new',
                        'sha'  => $entry['sha'] ?? '',
                        'size' => (int)($entry['size'] ?? 0),
                    ];
                } elseif (git_blob_sha($local) !== ($entry['sha'] ?? '')) {
                    $diff[] = [
                        'path' => $rel,
                        'type' => 'changed',
                        'sha'  => $entry['sha'] ?? '',
                        'size' => (int)($entry['size'] ?? 0),
                    ];
                }
            }
        }
    }
}

// ── 4. step 3: 변경 파일을 raw URL 로 받아 덮어쓰기 ────────────────────────
if ($step === 3 && $treeFetched && empty($errors)) {
    foreach ($diff as $item) {
        $rel = $item['path'];
        $rawUrl = "https://raw.githubusercontent.com/{$repo}/" . BRANCH . "/" . str_replace('%2F', '/', rawurlencode($rel));
        $content = http_get($rawUrl, null, 60);
        if ($content === null) {
            $applyFail[] = $rel . ' (다운로드 실패)';
            continue;
        }
        // git-blob SHA 검증 (다운로드 무결성)
        if (sha1("blob " . strlen($content) . "\0" . $content) !== $item['sha']) {
            $applyFail[] = $rel . ' (SHA 불일치 — 손상된 응답?)';
            continue;
        }
        $dst = $baseDir . '/' . $rel;
        $dir = dirname($dst);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (file_put_contents($dst, $content) === false) {
            $applyFail[] = $rel . ' (쓰기 실패 — 권한 확인)';
            continue;
        }
        $applied[] = $rel;
    }

    $log[] = "갱신 완료: " . count($applied) . "건"
           . ($applyFail ? ", 실패: " . count($applyFail) . "건" : "");
}

// ── 5. 렌더 ──────────────────────────────────────────────────────────────
$distroKind = preg_match('#_v7_(\w+)$#', $repo, $m) ? strtoupper($m[1]) : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SpeedMIS v7 (<?= htmlspecialchars($distroKind) ?>) Update</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Pretendard', -apple-system, sans-serif; background: #f4f5f7; color: #1a1d27; min-height: 100vh; padding: 40px 20px; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); width: 720px; max-width: 100%; margin: 0 auto; padding: 36px 40px; }
  h1 { font-size: 22px; margin-bottom: 6px; }
  .sub { color: #8c93b0; font-size: 14px; margin-bottom: 24px; }
  .tag { display:inline-block; font-size:11px; font-weight:700; color:#fff; background:#154AA0; border-radius:4px; padding:2px 8px; margin-bottom:14px; letter-spacing:.5px; }
  .meta { background:#f8f9fb; border:1px solid #dde0e8; border-radius:8px; padding:14px 18px; font-size:13px; line-height:1.8; margin-bottom:20px; }
  .meta b { color: #4a5068; min-width: 110px; display: inline-block; }
  .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; height: 42px; padding: 0 22px; border: 0; border-radius: 6px; font-size: 15px; font-weight: 600; background: #4f6ef7; color: #fff; cursor: pointer; transition: background 0.15s; text-decoration: none; }
  .btn:hover { background: #3b5de7; color:#fff; text-decoration:none; }
  .btn.btn-ghost { background:#f0f1f5; color:#4a5068; }
  .btn.btn-ghost:hover { background:#e6e8f0; color:#1a1d27; }
  .btn.btn-warn { background:#dc2626; }
  .btn.btn-warn:hover { background:#b91c1c; }
  .btn-row { display:flex; gap: 10px; margin-top: 20px; }
  .err { background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 12px; }
  .ok { background: #f0fdf4; border: 1px solid #86efac; color: #16a34a; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 12px; }
  .log { background: #f8f9fb; border: 1px solid #dde0e8; border-radius: 6px; padding: 12px 14px; font-size: 12px; margin-bottom: 16px; line-height: 1.7; font-family: ui-monospace, monospace; color: #4a5068; }
  .difflist { max-height: 420px; overflow-y: auto; border: 1px solid #dde0e8; border-radius: 6px; margin-bottom: 16px; }
  .difflist table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .difflist th, .difflist td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #f0f1f5; }
  .difflist th { background: #f8f9fb; font-weight: 600; color: #4a5068; font-size: 12px; position: sticky; top: 0; z-index: 1; }
  .difflist tr:last-child td { border-bottom: 0; }
  .difflist .ftype { font-size: 11px; padding: 1px 6px; border-radius: 3px; font-weight: 600; }
  .difflist .ftype.new { background:#dcfce7; color:#15803d; }
  .difflist .ftype.changed { background:#fef3c7; color:#a16207; }
  .difflist .fpath { font-family: ui-monospace, monospace; color:#1a1d27; }
  .difflist .fsize { color:#8c93b0; font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
  .count-summary { display:flex; gap:14px; margin: 12px 0 18px; font-size: 13px; }
  .count-summary span b { font-size: 17px; color:#1a1d27; margin-right: 3px; }
  .done-icon { font-size: 48px; text-align: center; margin-bottom: 16px; color:#16a34a; }
  a { color: #4f6ef7; text-decoration: none; }
  a:hover { text-decoration: underline; }
  details summary { cursor:pointer; font-size: 12px; color:#8c93b0; margin: 4px 0; }
  details ul { padding-left: 22px; font-size: 12px; color:#4a5068; font-family: ui-monospace, monospace; line-height: 1.7; }

  /* spinner */
  .btn .btn-spinner { display:none; width:14px; height:14px; border:2px solid rgba(255,255,255,0.45); border-top-color:#FFF; border-radius:50%; animation: spin 0.7s linear infinite; }
  .btn.is-loading .btn-spinner { display:inline-block; }
  .btn.is-loading { background:#3b5de7; cursor:wait; }
  @keyframes spin { to { transform: rotate(360deg); } }
  #overlay { display:none; position:fixed; inset:0; background:rgba(15,17,23,0.55); z-index:9999; align-items:center; justify-content:center; padding:20px; }
  #overlay .overlay-card { background:#fff; border-radius:14px; padding:32px 40px; text-align:center; max-width:420px; box-shadow:0 18px 60px rgba(0,0,0,0.25); }
  #overlay .overlay-spinner { width:44px; height:44px; border:4px solid #E5E8EB; border-top-color:#4F6EF7; border-radius:50%; margin:0 auto 16px; animation: spin 0.9s linear infinite; }
</style>
</head>
<body>
<div class="card">
  <span class="tag"><?= htmlspecialchars($distroKind) ?> UPDATE</span>
  <h1>파일 업데이트</h1>
  <p class="sub">설치된 소스 파일을 GitHub <code><?= htmlspecialchars($repo) ?></code> 의 최신본(<?= htmlspecialchars(BRANCH) ?>)과 비교하고 갱신합니다.</p>

  <div class="meta">
    <div><b>DB Driver</b> <?= htmlspecialchars($dbDriver) ?></div>
    <div><b>Distro Repo</b> <?= htmlspecialchars($repo) ?></div>
    <div><b>Install Path</b> <?= htmlspecialchars($baseDir) ?></div>
    <div><b>인증</b> <?= ($authUid === '__recovery__') ? '복구 키' : htmlspecialchars($authUid) ?></div>
  </div>

  <?php foreach ($errors as $e): ?><div class="err"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<?php if ($step === 1): ?>

  <p style="font-size:14px;color:#4a5068;line-height:1.7;margin-bottom:18px;">
    <strong>최신 버전 확인</strong> 을 누르면 원격과 로컬 파일을 git-blob SHA 로 비교해서 차이가 있는 파일 목록을 보여줍니다. 차이 확인 후 적용 여부를 결정하세요.
  </p>
  <p style="font-size:13px;color:#8c93b0;line-height:1.7;margin-bottom:18px;">
    보존 항목: <code>.env</code> · <code>logs/</code> · <code>uploadFiles/</code> · <code>db/</code> · <code>public/data/</code> · <code>update.php</code>(self)
  </p>
  <form method="post">
    <input type="hidden" name="step" value="2">
    <button type="submit" class="btn" id="check-btn">
      <span class="btn-spinner"></span>
      <span>최신 버전 확인</span>
    </button>
  </form>

<?php elseif ($step === 2): ?>

  <?php if (!$treeFetched): ?>
    <div class="err">원격 트리를 가져오지 못했습니다. 잠시 후 다시 시도하세요.</div>
    <a href="?step=1" class="btn btn-ghost">처음으로</a>
  <?php elseif (!$diff): ?>
    <div class="ok">로컬 파일이 최신본과 일치합니다. 갱신할 항목이 없습니다.</div>
    <p style="font-size:12px;color:#8c93b0;margin-bottom:18px;">latest commit: <code><?= htmlspecialchars(substr($latestSha, 0, 12)) ?></code></p>
    <a href="?step=1" class="btn btn-ghost">처음으로</a>
  <?php else: ?>
    <?php
      $cntNew = count(array_filter($diff, fn($d) => $d['type'] === 'new'));
      $cntChg = count($diff) - $cntNew;
      $totalSize = array_sum(array_column($diff, 'size'));
    ?>
    <div class="count-summary">
      <span><b><?= count($diff) ?></b>개 변경</span>
      <span style="color:#15803d;"><b><?= $cntNew ?></b>개 새 파일</span>
      <span style="color:#a16207;"><b><?= $cntChg ?></b>개 수정</span>
      <span style="color:#8c93b0;">총 <?= format_size($totalSize) ?></span>
      <span style="color:#8c93b0;margin-left:auto;">latest <code><?= htmlspecialchars(substr($latestSha, 0, 12)) ?></code></span>
    </div>

    <div class="difflist">
      <table>
        <thead><tr><th style="width:90px">상태</th><th>경로</th><th style="width:90px">크기</th></tr></thead>
        <tbody>
          <?php foreach ($diff as $d): ?>
            <tr>
              <td><span class="ftype <?= htmlspecialchars($d['type']) ?>"><?= $d['type'] === 'new' ? 'NEW' : 'CHANGED' ?></span></td>
              <td class="fpath"><?= htmlspecialchars($d['path']) ?></td>
              <td class="fsize"><?= format_size($d['size']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form method="post" id="apply-form">
      <input type="hidden" name="step" value="3">
      <div class="btn-row">
        <button type="submit" class="btn btn-warn" id="apply-btn">
          <span class="btn-spinner"></span>
          <span>위 <?= count($diff) ?>건 적용</span>
        </button>
        <a href="?step=1" class="btn btn-ghost">취소</a>
      </div>
      <p style="font-size:12px;color:#8c93b0;margin-top:14px;line-height:1.6;">
        주의: 갱신 후 PHP opcache 가 캐시된 이전 코드를 일정 시간 보유할 수 있습니다. 즉시 반영이 필요하면 PHP-FPM 을 reload 하세요 (셸 접근 가능 시 <code>sudo systemctl reload php8.4-fpm</code> 등). CSS/JS 는 base.php 의 filemtime cache-bust 가 자동 처리합니다.
      </p>
    </form>
  <?php endif; ?>

<?php elseif ($step === 3): ?>

  <?php if ($applied && !$applyFail): ?>
    <div class="done-icon">&#10004;</div>
    <h1 style="text-align:center">업데이트 완료!</h1>
    <p class="sub" style="text-align:center"><?= count($applied) ?>건 갱신됨</p>
  <?php elseif ($applied && $applyFail): ?>
    <div class="err">일부 파일 갱신 실패 (<?= count($applyFail) ?>건). 권한/네트워크 확인 후 재시도 권장.</div>
  <?php elseif (!$applied && $applyFail): ?>
    <div class="err">모든 파일 갱신 실패. 권한/네트워크 확인.</div>
  <?php else: ?>
    <div class="ok">갱신할 항목이 없습니다.</div>
  <?php endif; ?>

  <?php if ($applied): ?>
    <details open style="margin: 16px 0;">
      <summary>✓ 적용된 파일 (<?= count($applied) ?>)</summary>
      <ul><?php foreach ($applied as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?></ul>
    </details>
  <?php endif; ?>
  <?php if ($applyFail): ?>
    <details open style="margin: 16px 0;">
      <summary>✗ 실패 (<?= count($applyFail) ?>)</summary>
      <ul><?php foreach ($applyFail as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?></ul>
    </details>
  <?php endif; ?>

  <div class="btn-row">
    <a href="/" class="btn">사이트로 이동</a>
    <a href="?step=1" class="btn btn-ghost">한 번 더 확인</a>
  </div>

<?php endif; ?>

  <?php if (!empty($log)): ?>
    <details style="margin-top: 20px;">
      <summary>실행 로그</summary>
      <div class="log"><?php foreach ($log as $l): ?><?= htmlspecialchars($l) ?><br><?php endforeach; ?></div>
    </details>
  <?php endif; ?>
</div>

<div id="overlay">
  <div class="overlay-card">
    <div class="overlay-spinner"></div>
    <h2 style="font-size:18px;font-weight:700;margin-bottom:10px;color:#191F28">처리 중…</h2>
    <p style="font-size:14px;line-height:1.7;color:#4E5968">원격 트리 조회 또는 파일 다운로드가 진행 중입니다.<br>창을 닫지 마세요.</p>
  </div>
</div>

<script>
(function () {
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function () {
      var btn = f.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }
      var ov = document.getElementById('overlay');
      if (ov) ov.style.display = 'flex';
    });
  });
})();
</script>
</body>
</html>
