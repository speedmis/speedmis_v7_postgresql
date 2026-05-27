#!/usr/bin/env bash
# SpeedMIS v7 — 로컬 개발 서버 (Linux / Mac)
# 사용법: ./start.sh        (기본 포트 8080)
#         ./start.sh 9000   (다른 포트)
set -e

PORT="${1:-8080}"

# PHP 확인
if ! command -v php >/dev/null 2>&1; then
    echo "ERROR: PHP 가 설치되어 있지 않습니다."
    echo "  Ubuntu/Debian : sudo apt install php8.3-cli php8.3-pgsql php8.3-mbstring"
    echo "  macOS         : brew install php"
    exit 1
fi

# 포트 점유 검사
if lsof -i ":$PORT" >/dev/null 2>&1; then
    echo "ERROR: 포트 $PORT 가 이미 사용 중입니다. 다른 포트로 시도하세요:"
    echo "  ./start.sh 9000"
    exit 1
fi

echo "════════════════════════════════════════════════════════════"
echo "  SpeedMIS v7 (PostgreSQL) — 로컬 개발 서버"
echo "  브라우저에서 열어보세요: http://localhost:${PORT}/install.php"
echo "  종료: Ctrl+C"
echo "════════════════════════════════════════════════════════════"

exec php -S "0.0.0.0:${PORT}" router.php
