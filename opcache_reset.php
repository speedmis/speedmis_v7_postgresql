<?php
// 일회성 OPcache 리셋 — 사용 후 즉시 삭제 권장
header('Content-Type: text/plain; charset=utf-8');

if (function_exists('opcache_reset')) {
    $ok = opcache_reset();
    echo $ok ? "OPcache 리셋 성공\n" : "OPcache 리셋 실패\n";
} else {
    echo "OPcache 미설치 또는 비활성\n";
}

if (function_exists('opcache_get_status')) {
    $s = opcache_get_status(false);
    echo "\n[상태]\n";
    echo "enabled            : " . var_export($s['opcache_enabled'] ?? null, true) . "\n";
    echo "cached_scripts     : " . ($s['opcache_statistics']['num_cached_scripts'] ?? 0) . "\n";
    echo "validate_timestamps: " . (ini_get('opcache.validate_timestamps') ?: '0') . "\n";
    echo "revalidate_freq    : " . (ini_get('opcache.revalidate_freq') ?: '0') . "\n";
}
