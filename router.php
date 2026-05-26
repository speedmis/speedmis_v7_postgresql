<?php
/**
 * PHP 내장 웹서버 라우터
 * 사용: php -S localhost:8088 router.php
 */

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);

// 정적 파일 (public 폴더)
$staticFile = __DIR__ . '/public' . $path;
if ($path !== '/' && file_exists($staticFile) && !is_dir($staticFile)) {
    return false; // 내장 서버가 직접 처리
}

// API
if (str_starts_with($path, '/api.php') || str_contains($uri, 'act=')) {
    require __DIR__ . '/api.php';
    return true;
}

// SPA fallback
require __DIR__ . '/index.php';
return true;
