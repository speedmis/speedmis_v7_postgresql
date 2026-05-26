<?php

namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * install.php / envmanage.php 등 Slim 라우터 밖에서 돌아가는 독립 스크립트용 인증 헬퍼
 *
 * 규칙:
 *   - .env 미존재(최초 설치) → 통과 (install.php 만 이 상태 허용)
 *   - .env 존재 → access_token 쿠키의 JWT 를 검증하고
 *                 user_id ∈ (gadmin, admin) 인 사용자만 허용
 */
class InstallAuth
{
    /** 허용되는 관리 계정 user_id */
    private const ALLOWED_UIDS = ['gadmin', 'admin'];

    /** 복구 키 해시 저장 환경변수 (.env) */
    private const RECOVERY_KEY_ENV = 'INSTALL_RECOVERY_HASH';
    /** 복구 키 쿠키명 — 1시간 유효 */
    private const RECOVERY_COOKIE  = 'install_recovery';
    private const RECOVERY_TTL     = 3600;
    /** 복구 키 최소 길이 */
    private const RECOVERY_MIN_LEN = 8;

    /**
     * 요청 사이트의 .env 경로를 결정.
     * envmanage.php / install.php / 본 클래스가 운영에서 symlink 로 여러 사이트에 공유될 수 있어
     * __DIR__ 사용 금지 — 항상 실파일(=symlink target) 위치로 풀려 모든 사이트가 같은 .env 를 가리킴.
     * 웹서버가 셋팅한 SCRIPT_FILENAME (symlink 미해석) 의 dirname 이 요청 사이트의 webroot.
     */
    public static function resolveEnvPath(): string
    {
        if (!empty($_SERVER['SCRIPT_FILENAME'])) {
            return dirname($_SERVER['SCRIPT_FILENAME']) . '/.env';
        }
        return dirname(__DIR__, 2) . '/.env';
    }

    /**
     * 현재 요청의 관리자 uid 반환. 검증 실패 시 null.
     */
    public static function currentAdminUid(): ?string
    {
        $token = $_COOKIE['access_token'] ?? '';
        if ($token === '') return null;

        $envPath = self::resolveEnvPath();
        if (!is_file($envPath)) return null;

        $env = self::parseEnvFile($envPath);
        $secret = $env['APP_PWD_KEY'] ?? '';
        if ($secret === '') return null;

        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) return null;
        require_once $autoload;

        try {
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable) {
            return null;
        }

        if (($payload->type ?? '') !== 'access') return null;

        $uid = (string)($payload->uid ?? '');
        if ($uid === '') return null;
        if (!in_array($uid, self::ALLOWED_UIDS, true)) return null;

        // DB 로 사용자 상태 검증 (useflag=1) — DB_DRIVER 별 DSN
        try {
            $driver = strtolower($env['DB_DRIVER'] ?? 'mysql');
            $host   = $env['DB_HOST'] ?? '127.0.0.1';
            $port   = $env['DB_PORT'] ?? ($driver === 'pgsql' ? '5432' : ($driver === 'sqlsrv' ? '1433' : '3306'));
            $name   = $env['DB_NAME'] ?? '';
            $charset = $env['DB_CHARSET'] ?? 'utf8mb4';
            if ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$name};connect_timeout=2";
            } elseif ($driver === 'sqlsrv') {
                // MSSQL: PDO::ATTR_TIMEOUT 미지원이라 DSN 의 LoginTimeout 사용
                $dsn = "sqlsrv:Server={$host},{$port};Database={$name};TrustServerCertificate=true;Encrypt=optional;LoginTimeout=2";
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            }
            $opts = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
            if ($driver !== 'sqlsrv') $opts[\PDO::ATTR_TIMEOUT] = 2;
            $pdo = new \PDO($dsn, $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', $opts);
            $sqlSel = $driver === 'sqlsrv'
                ? 'SELECT TOP 1 useflag FROM mis_users WHERE user_id = ?'
                : 'SELECT useflag FROM mis_users WHERE user_id = ? LIMIT 1';
            $stmt = $pdo->prepare($sqlSel);
            $stmt->execute([$uid]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row || ($row['useflag'] ?? '') !== '1') return null;
        } catch (\Throwable) {
            // DB 접속 실패 시에도 JWT 가 유효하고 uid 가 whitelist 면 통과
            // (.env DB 수정 직후 등 DB 접속 불가 시 자기차단 방지)
        }

        return $uid;
    }

    /**
     * install.php 등에서 호출 — admin 로그인 우선, 실패 시 복구 키 게이트.
     * 통과 시 uid 또는 '__recovery__' 반환. 실패 시 화면 출력 후 exit.
     */
    public static function requireAccess(string $pageName = '관리 페이지'): string
    {
        $uid = self::currentAdminUid();
        if ($uid !== null) return $uid;

        if (self::handleRecoveryGate($pageName)) {
            return '__recovery__';
        }
        exit; // 게이트 화면이 출력되었음
    }

    /**
     * 복구 키 게이트.
     *  - 쿠키 인증 OK → true
     *  - POST 로 키 입력 → 검증/최초저장 후 쿠키 설정 → true
     *  - 그 외 → 입력 폼 출력 → false (호출측은 exit 해야 함)
     *
     * 동작:
     *  - .env 에 INSTALL_RECOVERY_HASH 가 없으면 "최초 설정" 모드 (누구나 설정 가능)
     *  - 있으면 "키 입력" 모드
     */
    public static function handleRecoveryGate(string $pageName): bool
    {
        if (self::currentRecoveryAccess()) return true;

        $envPath    = self::resolveEnvPath();
        $env        = is_file($envPath) ? self::parseEnvFile($envPath) : [];
        $hashStored = (string)($env[self::RECOVERY_KEY_ENV] ?? '');

        $err = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['recovery_key'])) {
            $key = (string)$_POST['recovery_key'];
            if (strlen($key) < self::RECOVERY_MIN_LEN) {
                $err = '복구 키는 ' . self::RECOVERY_MIN_LEN . '자 이상이어야 합니다.';
            } elseif ($hashStored === '') {
                // 최초 설정
                $newHash = password_hash($key, PASSWORD_BCRYPT);
                if (!self::writeEnvMerge($envPath, [self::RECOVERY_KEY_ENV => $newHash])) {
                    $err = '.env 저장 실패 — 파일 쓰기 권한 확인 필요';
                } else {
                    self::setRecoveryCookie($key);
                    // POST-Redirect-GET
                    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/install.php'));
                    return false; // exit 는 install.php 가 처리
                }
            } elseif (password_verify($key, $hashStored)) {
                self::setRecoveryCookie($key);
                header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/install.php'));
                return false;
            } else {
                $err = '복구 키가 일치하지 않습니다.';
            }
        }

        self::renderRecoveryGate($pageName, $hashStored !== '', $err);
        return false;
    }

    /**
     * 쿠키만으로 복구 인증 통과 여부 확인 (form 처리 없음)
     */
    public static function currentRecoveryAccess(): bool
    {
        $envPath = self::resolveEnvPath();
        if (!is_file($envPath)) return false;
        $env  = self::parseEnvFile($envPath);
        $hash = (string)($env[self::RECOVERY_KEY_ENV] ?? '');
        if ($hash === '') return false;
        $cookie = (string)($_COOKIE[self::RECOVERY_COOKIE] ?? '');
        if ($cookie === '') return false;
        return password_verify($cookie, $hash);
    }

    private static function setRecoveryCookie(string $key): void
    {
        setcookie(self::RECOVERY_COOKIE, $key, [
            'expires'  => time() + self::RECOVERY_TTL,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public static function renderRecoveryGate(string $pageName, bool $hashExists, string $err = ''): void
    {
        header('HTTP/1.1 401 Unauthorized');
        $safeName = htmlspecialchars($pageName, ENT_QUOTES, 'UTF-8');
        $safeErr  = htmlspecialchars($err,      ENT_QUOTES, 'UTF-8');
        $title    = $hashExists ? '복구 키 입력' : '복구 키 최초 설정';
        $hint     = $hashExists
            ? '저장된 복구 키를 입력하면 접근할 수 있습니다.'
            : '복구 키가 아직 설정되지 않았습니다. 지금 설정하면 .env 에 해시로 저장됩니다. (최소 ' . self::RECOVERY_MIN_LEN . '자, 안전한 곳에 보관)';
        $btnLabel = $hashExists ? '확인' : '설정 후 진입';
        $errBlock  = $err === '' ? '' : "<div class='err'>{$safeErr}</div>";
        $minLenVal = self::RECOVERY_MIN_LEN;

        echo <<<HTML
<!DOCTYPE html><html lang="ko"><head>
<meta charset="utf-8"><title>{$title} — SpeedMIS</title>
<style>
 body{font-family:Pretendard,system-ui,sans-serif;background:#F4F5F7;margin:0;padding:80px 16px;color:#1A1D27;text-align:center}
 .box{max-width:440px;margin:0 auto;background:#fff;padding:32px 28px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08);border:1px solid #DDE0E8;text-align:left}
 h1{font-size:18px;margin:0 0 14px;text-align:center}
 p{color:#4A5068;font-size:14px;line-height:1.6;margin:8px 0}
 input{width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;border:1px solid #DDE0E8;border-radius:6px;margin:14px 0;outline:none}
 input:focus{border-color:#4F6EF7}
 button{width:100%;padding:11px;background:#4F6EF7;color:#fff;border:0;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer}
 button:hover{background:#3e5bd9}
 .err{color:#D33;font-size:13px;margin:10px 0;background:#fff5f5;padding:8px 10px;border-radius:4px;border:1px solid #fcc}
 .hint{color:#8C93B0;font-size:12px;margin-top:18px;text-align:center}
 a{color:#4F6EF7;text-decoration:none}
</style>
</head><body>
<div class="box">
 <h1>🔐 {$title}</h1>
 <p>로그인이 불가한 비상 상황을 대비한 복구 키입니다.<br><b>{$safeName}</b> 접근에 사용됩니다.</p>
 <p>{$hint}</p>
 {$errBlock}
 <form method="post">
   <input type="password" name="recovery_key" autofocus required minlength="{$minLenVal}" placeholder="복구 키">
   <button type="submit">{$btnLabel}</button>
 </form>
 <p class="hint"><a href="/">← 메인(로그인) 페이지로</a></p>
</div>
</body></html>
HTML;
    }

    /**
     * 로그인 필요 안내 페이지 출력
     */
    public static function renderLoginRequired(string $pageName = '관리 페이지'): void
    {
        $uidHint = isset($_COOKIE['access_token']) ? '(세션 만료 또는 권한 부족)' : '';
        header('HTTP/1.1 401 Unauthorized');
        $loginUrl = '/';
        $safeName = htmlspecialchars($pageName, ENT_QUOTES, 'UTF-8');
        $safeHint = htmlspecialchars($uidHint, ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!DOCTYPE html><html lang="ko"><head>
<meta charset="utf-8"><title>관리자 인증 필요 — SpeedMIS</title>
<style>
 body{font-family:Pretendard,system-ui,sans-serif;background:#F4F5F7;margin:0;padding:80px 16px;color:#1A1D27;text-align:center}
 .box{max-width:420px;margin:0 auto;background:#fff;padding:32px 28px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08);border:1px solid #DDE0E8}
 h1{font-size:18px;margin:0 0 12px}
 p{color:#4A5068;font-size:14px;line-height:1.6;margin:8px 0}
 a.btn{display:inline-block;margin-top:18px;padding:10px 20px;background:#4F6EF7;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:600}
 a.btn:hover{background:#3e5bd9}
 .hint{color:#8C93B0;font-size:12px;margin-top:16px}
</style>
</head><body>
<div class="box">
 <h1>🔒 관리자 인증 필요</h1>
 <p>{$safeName}은 <b>gadmin</b> 또는 <b>admin</b> 계정으로 로그인한 후에만 접근할 수 있습니다.</p>
 <p class="hint">{$safeHint}</p>
 <a class="btn" href="{$loginUrl}">로그인 페이지로 이동</a>
</div>
</body></html>
HTML;
    }

    /**
     * .env 파일 → 연관배열 (간단 파싱, 따옴표 제거)
     */
    public static function parseEnvFile(string $path): array
    {
        $out = [];
        if (!is_file($path)) return $out;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = ltrim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($v !== '' && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                $v = substr($v, 1, -1);
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /**
     * 연관배열 → .env 텍스트. 원본 줄 순서/주석을 보존하려면 writeEnvMerge 를 사용할 것.
     */
    public static function buildEnvText(array $data): string
    {
        $lines = [];
        foreach ($data as $k => $v) {
            $v = (string)$v;
            // 공백/특수문자가 있으면 따옴표로 감싸기
            if ($v === '' || preg_match('/^[A-Za-z0-9_.\/:@\-]+$/', $v)) {
                $lines[] = "{$k}={$v}";
            } else {
                $escaped = str_replace('"', '\"', $v);
                $lines[] = "{$k}=\"{$escaped}\"";
            }
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * 기존 .env 의 주석/순서 보존하며 값만 업데이트. 새 키는 파일 끝에 추가.
     */
    public static function writeEnvMerge(string $path, array $updates): bool
    {
        $existing = is_file($path)
            ? file($path, FILE_IGNORE_NEW_LINES)
            : [];
        $seen = [];
        foreach ($existing as $i => $line) {
            $trim = ltrim($line);
            if ($trim === '' || $trim[0] === '#') continue;
            if (!str_contains($trim, '=')) continue;
            [$k] = explode('=', $trim, 2);
            $k = trim($k);
            if (array_key_exists($k, $updates)) {
                $v = (string)$updates[$k];
                if ($v === '' || preg_match('/^[A-Za-z0-9_.\/:@\-]+$/', $v)) {
                    $existing[$i] = "{$k}={$v}";
                } else {
                    $escaped = str_replace('"', '\"', $v);
                    $existing[$i] = "{$k}=\"{$escaped}\"";
                }
                $seen[$k] = true;
            }
        }
        foreach ($updates as $k => $v) {
            if (empty($seen[$k])) {
                $v = (string)$v;
                if ($v === '' || preg_match('/^[A-Za-z0-9_.\/:@\-]+$/', $v)) {
                    $existing[] = "{$k}={$v}";
                } else {
                    $escaped = str_replace('"', '\"', $v);
                    $existing[] = "{$k}=\"{$escaped}\"";
                }
            }
        }
        // 백업
        if (is_file($path)) {
            @copy($path, $path . '.bak.' . date('Ymd_His'));
        }
        $content = implode("\n", $existing);
        if (!str_ends_with($content, "\n")) $content .= "\n";
        return file_put_contents($path, $content) !== false;
    }

    /**
     * 민감 필드 여부 (폼에서 마스킹 처리)
     */
    public static function isSensitive(string $key): bool
    {
        $lower = strtolower($key);
        foreach (['pass', 'secret', 'token', 'key', 'pwd'] as $needle) {
            if (str_contains($lower, $needle)) return true;
        }
        return false;
    }
}
