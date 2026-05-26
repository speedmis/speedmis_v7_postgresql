<?php
/**
 * speedmy006004_treat — 메일전송 테스트 treat 훅
 * speedmy006004.php 의 폼 POST 시 api.php (act=serverOnly) 가 자동 호출.
 * .env 의 MAIL_FROM_ADDRESS / MAIL_FROM_NAME / MAIL_CHARSET / MAIL_FROM 등을 사용.
 * SMTP 라이브러리 없이 PHP mail() (postfix sendmail 백엔드) — 첨부 시 multipart 직접 조립.
 */

function addLogic_treat(array &$result): void {
    $from     = trim((string)($_ENV['MAIL_FROM_ADDRESS'] ?? ''));
    $fromName = trim((string)($_ENV['MAIL_FROM_NAME']    ?? ''));
    $charset  = trim((string)($_ENV['MAIL_CHARSET']      ?? '')) ?: 'utf-8';

    $to       = trim((string)($result['tomail']   ?? ''));
    $rndIn    = trim((string)($result['rnd']      ?? ''));
    $isAttach = (string)($result['isAttach'] ?? 'N') === 'Y';
    $rndCk    = trim((string)($_COOKIE['rnd']     ?? ''));

    // 1) 검증
    if ($from === '')                                         { _speedmy006004_finish($result, false, '발신주소(.env MAIL_FROM_ADDRESS) 미설정.'); return; }
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)){ _speedmy006004_finish($result, false, '수신메일주소 형식 오류.'); return; }
    if ($rndCk === '' || $rndIn === '' || $rndCk !== $rndIn) { _speedmy006004_finish($result, false, '확인번호가 일치하지 않습니다. 페이지 새로고침 후 다시 시도.'); return; }

    // 2) 본문 / 첨부
    $subject = "speedmis 를 통한 메일전송 demo 입니다. {$rndCk}";
    $body    = '<b>speedmis 를 통한 메일전송 demo 입니다.</b><br><br>감사합니다.';

    $attachments = [];
    if ($isAttach) {
        // 데모용 첨부 — 운영에 실제 존재하는 작은 파일 2개 골라서 사용
        // (없으면 첨부 없이 폴백)
        foreach ([
            BASE_PATH . '/public/apple-touch-icon.png',
            BASE_PATH . '/public/favicon.ico',
        ] as $p) {
            if (is_file($p)) $attachments[] = ['path' => $p, 'name' => basename($p)];
        }
    }

    // 3) 발송
    [$ok, $detail] = _speedmy006004_send($to, $from, $fromName, $subject, $body, $charset, $attachments);

    // 4) 새 rnd 발급 — 다음 전송 위해
    $newRnd = (string)random_int(1000, 9999);
    setcookie('rnd', $newRnd, ['path' => '/', 'samesite' => 'Lax']);
    $_COOKIE['rnd'] = $newRnd;

    $msg = ($ok ? ($isAttach ? '첨부 ' . count($attachments) . '개 포함 ' : '첨부 없이 ') . '전송이 완료되었습니다.' : '발송 실패')
         . " — {$detail} (rnd={$rndCk})";
    _speedmy006004_finish($result, $ok, $msg);
}

/** 결과 메시지를 $GLOBALS['_treat_result'] 로 노출 — speedmy006004.php 가 읽어 화면 표시 */
function _speedmy006004_finish(array &$result, bool $ok, string $msg): void {
    $result['_ok']  = $ok;
    $result['_msg'] = $msg;
}

/**
 * 메일 발송 — postfix sendmail 통한 PHP mail() 사용 (SMTP 라이브러리 없이).
 * 첨부 있으면 multipart/mixed 직접 조립.
 */
function _speedmy006004_send(
    string $to, string $from, string $fromName, string $subject, string $bodyHtml,
    string $charset, array $attachments
): array {
    $fromHeader = $fromName !== ''
        ? '=?' . $charset . '?B?' . base64_encode($fromName) . '?= <' . $from . '>'
        : $from;
    $subjectHeader = '=?' . $charset . '?B?' . base64_encode($subject) . '?=';

    if (empty($attachments)) {
        $headers  = "From: {$fromHeader}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset={$charset}\r\n";
        $ok = @mail($to, $subjectHeader, $bodyHtml, $headers);
        return [$ok, $ok ? 'sendmail OK' : 'mail() 호출 실패 — postfix 상태 확인 필요'];
    }

    $boundary = '----v7mail-' . bin2hex(random_bytes(8));
    $headers  = "From: {$fromHeader}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $msg  = "--{$boundary}\r\n";
    $msg .= "Content-Type: text/html; charset={$charset}\r\n";
    $msg .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $msg .= $bodyHtml . "\r\n";

    foreach ($attachments as $att) {
        $path = (string)($att['path'] ?? '');
        if (!is_file($path)) continue;
        $name = (string)($att['name'] ?? basename($path));
        $data = chunk_split(base64_encode((string)file_get_contents($path)));
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: application/octet-stream; name=\"{$name}\"\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
        $msg .= $data . "\r\n";
    }
    $msg .= "--{$boundary}--\r\n";

    $ok = @mail($to, $subjectHeader, $msg, $headers);
    return [$ok, $ok ? 'sendmail OK (multipart)' : 'mail() 호출 실패 — postfix 상태 확인 필요'];
}
