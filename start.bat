@echo off
REM SpeedMIS v7 — 로컬 개발 서버 (Windows)
REM 사용법: start.bat         (기본 포트 8080)
REM         start.bat 9000    (다른 포트)
setlocal

set PORT=%1
if "%PORT%"=="" set PORT=8080

where php >nul 2>&1
if errorlevel 1 (
    echo ERROR: PHP 가 설치되어 있지 않습니다.
    echo   다운로드: https://windows.php.net/download/
    exit /b 1
)

echo ============================================================
echo   SpeedMIS v7 ^(PostgreSQL^) - 로컬 개발 서버
echo   브라우저에서 열어보세요: http://localhost:%PORT%/install.php
echo   종료: Ctrl+C
echo ============================================================

php -S 0.0.0.0:%PORT% router.php
