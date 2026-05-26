<?php
/**
 * speedmy006004 — 메일전송 테스트 (menu_type='22' 서버로직만)
 *
 * 동작:
 *  - api.php?act=serverOnly&gubun=6004 → MainContent.jsx 의 iframe 안에서 호출됨
 *  - GET: 폼 출력 (수신주소 + 랜덤번호 확인)
 *  - POST: speedmy006004_treat.php 의 addLogic_treat() 가 메일 발송 후 결과 메시지 $GLOBALS['_treat_result']['_msg'] 에 셋팅
 *  - .env 의 MAIL_FROM_ADDRESS / MAIL_FROM_NAME / MAIL_CHARSET 사용
 *
 * 봇 차단용 랜덤 확인번호 — 쿠키 'rnd' 4자리 숫자 ↔ 폼 input 값 일치 시에만 발송.
 */

function pageLoad(): void {
    $from     = trim((string)($_ENV['MAIL_FROM_ADDRESS'] ?? ''));
    $fromName = trim((string)($_ENV['MAIL_FROM_NAME']    ?? ''));

    // 봇방지용 4자리 숫자 — 쿠키에 없으면 새로 발급
    if (empty($_COOKIE['rnd'])) {
        $rnd = (string)random_int(1000, 9999);
        setcookie('rnd', $rnd, [
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
        $_COOKIE['rnd'] = $rnd;
    }
    $rnd = (string)$_COOKIE['rnd'];

    // POST 결과 메시지 (있으면)
    $msg    = $GLOBALS['_treat_result']['_msg']      ?? '';
    $msgOk  = (bool)($GLOBALS['_treat_result']['_ok'] ?? false);
    $tomail = htmlspecialchars((string)($GLOBALS['_serverOnly_body']['tomail'] ?? $GLOBALS['_serverOnly_params']['tomail'] ?? ''));

    if ($from === '') {
        $msg   = '먼저 envmanage.php 에서 MAIL_FROM_ADDRESS 등 SMTP 항목을 설정하세요.';
        $msgOk = false;
    }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>메일전송 테스트</title>
<style>
 :root{--bg:#F4F5F7;--surface:#fff;--border:#DDE0E8;--text:#1A1D27;--sub:#4A5068;--muted:#8C93B0;--accent:#4F6EF7;--success:#10B981;--danger:#EF4444}
 @media (prefers-color-scheme: dark) {
   :root{--bg:#0F1117;--surface:#1A1D27;--border:#2E3250;--text:#E8EAF0;--sub:#9CA3C4;--muted:#5C6389}
 }
 *{box-sizing:border-box}
 body{margin:0;font-family:Pretendard,system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);font-size:14px;line-height:1.5;padding:20px}
 .box{max-width:720px;margin:0 auto;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:24px}
 h2{margin:0 0 16px;font-size:18px;font-weight:700}
 .row{display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
 .label{color:var(--sub);font-size:12px;min-width:120px}
 .value{color:var(--text);font-weight:600;word-break:break-all}
 .value.code{font-family:ui-monospace,Menlo,monospace;background:rgba(127,127,127,0.12);padding:2px 6px;border-radius:4px}
 hr{border:0;border-top:1px solid var(--border);margin:18px 0}
 form{margin:0}
 input[type=text]{padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px;background:var(--surface);color:var(--text);width:280px;font-family:inherit}
 input[type=text]:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(79,110,247,.18)}
 button{padding:8px 16px;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:6px;font-size:13.5px;cursor:pointer;font-weight:600;font-family:inherit}
 button:hover{background:rgba(79,110,247,0.08);border-color:var(--accent);color:var(--accent)}
 button.primary{background:var(--accent);color:#fff;border-color:var(--accent)}
 button.primary:hover{background:#3e5bd9;color:#fff}
 .msg{margin-top:18px;padding:14px;border-radius:6px;border:1px solid;font-weight:600}
 .msg.ok  {background:rgba(16,185,129,0.10);border-color:var(--success);color:var(--success)}
 .msg.err {background:rgba(239,68,68,0.10); border-color:var(--danger); color:var(--danger)}
 .hint{color:var(--muted);font-size:12px;margin-top:6px}
</style>
</head>
<body>
<div class="box">
  <h2>📧 메일전송 테스트</h2>

  <div class="row">
    <span class="label">발신메일주소</span>
    <span class="value"><?= htmlspecialchars($from ?: '(미설정)') ?><?= $fromName !== '' ? ' <span class="muted">(' . htmlspecialchars($fromName) . ')</span>' : '' ?></span>
  </div>
  <div class="row">
    <span class="label">SMTP HOST</span>
    <span class="value"><?= htmlspecialchars((string)($_ENV['MAIL_HOST'] ?? '(미설정 — postfix sendmail 사용)')) ?></span>
  </div>

  <hr>

  <p style="margin:0 0 14px">
    아래에 수신메일주소와 <strong><span class="value code"><?= htmlspecialchars($rnd) ?></span></strong> 값을 입력 후 전송.
  </p>

  <form method="post" action="">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)($GLOBALS['_csrf_token'] ?? $_COOKIE['csrf_token'] ?? '')) ?>">
    <div class="row">
      <input type="text" name="tomail"  placeholder="수신메일주소" value="<?= $tomail ?>" required>
      <input type="text" name="rnd"     placeholder="숫자네자리"   pattern="[0-9]{4}"     maxlength="4" required>
    </div>
    <div class="row">
      <button type="submit" name="isAttach" value="N">첨부없이 전송</button>
      <button type="submit" name="isAttach" value="Y" class="primary">첨부 2개 포함 전송</button>
    </div>
    <div class="hint">※ 첨부파일은 데모용 (서버 로컬 파일 2개)</div>
  </form>

  <?php if ($msg !== ''): ?>
    <div class="msg <?= $msgOk ? 'ok' : 'err' ?>">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif ?>
</div>
</body>
</html>
<?php
}
