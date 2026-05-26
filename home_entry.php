<?php
/**
 * 루트(/) 진입 라우터
 * .env 의 ROOT_REDIRECT_TO_APP = Y 이면 /v7 로 자동이동, 아니면 홈페이지(home/index.html) 서빙
 */

require_once __DIR__ . '/vendor/autoload.php';
try {
    \Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
} catch (\Throwable) {}

$redirect = strtoupper(trim((string)($_ENV['ROOT_REDIRECT_TO_APP'] ?? 'N'))) === 'Y';

if ($redirect) {
    header('Location: /v7', true, 302);
    exit;
}

// 홈페이지 HTML 그대로 스트림 (readfile 은 MIME 자동 설정 안 함)
$file = __DIR__ . '/home/index.html';
if (is_file($file)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($file);
    exit;
}

http_response_code(404);
echo '홈페이지 파일이 없습니다.';
