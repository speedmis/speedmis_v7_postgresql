<?php
/**
 * 공통 로직 — 모든 프로그램에 자동 적용
 *
 * 함수명 규칙: common_ + 훅이름
 *   common_pageLoad()         → 개별 pageLoad() 보다 먼저 실행
 *   common_before_query()     → 개별 before_query() 보다 먼저 실행
 *   common_list_json_init()   → 개별 list_json_init() 보다 먼저 실행
 *   common_list_json_load()   → 개별 list_json_load() 보다 먼저 실행
 *   common_save_updateReady() → 개별 save_updateReady() 보다 먼저 실행
 *   common_save_updateAfter() → 개별 save_updateAfter() 보다 먼저 실행
 *   common_save_writeAfter()  → 개별 save_writeAfter() 보다 먼저 실행
 *   ... (모든 훅에 대해 common_ 접두어 사용 가능)
 *
 * 실행 순서: common_훅 → 개별_훅
 * 파일 위치: programs/_common.php (이 파일)
 */

/**
 * 공통 pageLoad — 모든 프로그램 로드 시 실행
 */
function common_pageLoad() {
    global $misSessionUserId, $misSessionIsAdmin, $real_pid, $gubun;

    // 예: 접속 로그 기록
    // global $__pdo;
    // $__pdo->prepare("INSERT INTO mis_access_log (user_id, real_pid, gubun, wdate)
    //     VALUES (?, ?, ?, NOW())")->execute([$misSessionUserId, $real_pid, $gubun]);
}

/**
 * 공통 before_query — 모든 쿼리 빌드 전 실행
 */
// function common_before_query($menu, $fields, $params) {
//     global $misSessionUserId, $misSessionIsAdmin;
//     // 예: 관리자가 아니면 특정 조건 강제
// }

/**
 * 공통 list_json_init — 모든 목록 로딩 전 실행
 */
// function common_list_json_init() {
//     global $misSessionUserId, $isFirstLoad;
//     // 예: 최초 로딩 시 공통 알림
//     // if ($isFirstLoad) $GLOBALS['_client_toast'] = '환영합니다!';
// }

/**
 * 공통 save_updateAfter — 모든 UPDATE 완료 후 실행
 */
// function common_save_updateAfter($idx, &$afterScript) {
//     global $misSessionUserId, $gubun, $real_pid, $__pdo;
//     // 예: 모든 수정에 대한 히스토리 기록
//     // $__pdo->prepare("INSERT INTO mis_change_log (gubun, real_pid, record_idx, user_id, action, wdate)
//     //     VALUES (?, ?, ?, ?, 'update', NOW())")->execute([$gubun, $real_pid, $idx, $misSessionUserId]);
// }

/**
 * 공통 save_writeAfter — 모든 INSERT 완료 후 실행
 */
// function common_save_writeAfter($newIdx, &$afterScript) {
//     global $misSessionUserId, $gubun, $real_pid, $__pdo;
//     // 예: 신규 등록 알림
//     // $GLOBALS['_client_toast'] = "#{$newIdx} 등록 완료";
// }

/**
 * 공통 save_deleteBefore — 모든 삭제 전 검증
 */
// function common_save_deleteBefore($idx, &$cancelDelete) {
//     global $misSessionIsAdmin;
//     // 예: 관리자만 삭제 허용
//     // if ($misSessionIsAdmin !== 'Y') {
//     //     $cancelDelete = true;
//     //     $GLOBALS['_client_alert'] = '관리자만 삭제할 수 있습니다.';
//     // }
// }


// ═══════════════════════════════════════════════════════════════════════════
// v6 호환 헬퍼 — 레거시 PHP 코드에서 사용되는 VB 스타일 함수들
// ═══════════════════════════════════════════════════════════════════════════

if (!function_exists('requestVB')) {
    function requestVB(string $key, $default = ''): string {
        return (string)($_POST[$key] ?? $_GET[$key] ?? $default);
    }
}

if (!function_exists('InStr')) {
    // VB: 1-based 위치 반환, 없으면 0 / PHP strpos 는 0-based + false
    function InStr(?string $haystack, ?string $needle): int {
        if ($haystack === null || $haystack === '' || $needle === null || $needle === '') return 0;
        $p = strpos($haystack, $needle);
        return $p === false ? 0 : $p + 1;
    }
}

if (!function_exists('splitVB')) {
    function splitVB(string $str, string $delim): array {
        if ($delim === '') return [$str];
        return explode($delim, $str);
    }
}

if (!function_exists('iif')) {
    function iif($cond, $whenTrue, $whenFalse) {
        return $cond ? $whenTrue : $whenFalse;
    }
}

if (!function_exists('Left')) {
    function Left(string $s, int $n): string { return mb_substr($s, 0, max(0, $n), 'UTF-8'); }
}
if (!function_exists('Right')) {
    function Right(string $s, int $n): string {
        $len = mb_strlen($s, 'UTF-8');
        return mb_substr($s, max(0, $len - $n), min($n, $len), 'UTF-8');
    }
}
if (!function_exists('Mid')) {
    // VB Mid(s, start, len) — start 는 1-based
    function Mid(string $s, int $start, ?int $len = null): string {
        $start = max(1, $start) - 1;
        return $len === null ? mb_substr($s, $start, null, 'UTF-8') : mb_substr($s, $start, $len, 'UTF-8');
    }
}
if (!function_exists('Len')) {
    function Len(string $s): int { return mb_strlen($s, 'UTF-8'); }
}
if (!function_exists('replace')) {
    function replace(string $s, string $from, string $to): string { return str_replace($from, $to, $s); }
}
if (!function_exists('Trim')) {
    // v6: 양쪽 공백 제거 (PHP trim 의 case-insensitive 별칭)
    function Trim(?string $s): string { return trim((string)$s); }
}
if (!function_exists('uni_left')) {
    // v6: 멀티바이트 안전한 좌측 N자 자르기
    function uni_left(?string $s, int $n): string {
        if ($s === null || $s === '' || $n <= 0) return '';
        return mb_substr($s, 0, $n, 'UTF-8');
    }
}
if (!function_exists('FormatNum')) {
    // v6: 숫자 포맷. '0000000000' 처럼 zero-pad 패턴 지원, '#,##0' 천단위 콤마 지원.
    function FormatNum($num, string $fmt = '#,##0'): string {
        if ($num === null || $num === '') return '';
        if (preg_match('/^0+$/', $fmt)) {
            return str_pad((string)$num, strlen($fmt), '0', STR_PAD_LEFT);
        }
        if ($fmt === '#,##0' || $fmt === '#,###') {
            return number_format((float)$num);
        }
        return (string)$num;
    }
}
if (!function_exists('gzecho')) {
    // v6: 출력 헬퍼 (압축 옵션은 v7 에선 단순 echo). 외부 스크립트(QR 프린터 등) 가 expect 하는 형식.
    function gzecho($s): void {
        echo (string)$s;
    }
}
if (!function_exists('request_cookies')) {
    // v6: 쿠키 값 반환. 없으면 빈 문자열.
    function request_cookies(string $name): string {
        return (string)($_COOKIE[$name] ?? '');
    }
}
if (!function_exists('request_post')) {
    // v6: POST 값 반환
    function request_post(string $name): string {
        return (string)($_POST[$name] ?? '');
    }
}
if (!function_exists('request_get')) {
    // v6: GET 값 반환
    function request_get(string $name): string {
        return (string)($_GET[$name] ?? '');
    }
}
if (!function_exists('onlyOnereturnSql')) {
    // v6: 쿼리 실행해서 첫 행 첫 컬럼 반환
    function onlyOnereturnSql(string $sql): mixed {
        global $__pdo;
        if (!$__pdo) return '';
        try {
            $stmt = $__pdo->query($sql);
            $v = $stmt->fetchColumn();
            return $v === false ? '' : $v;
        } catch (\Throwable) {
            return '';
        }
    }
}
if (!function_exists('allreturnSql')) {
    // v6: 쿼리 실행해서 전체 행(연관 배열) 반환
    function allreturnSql(string $sql): array {
        global $__pdo;
        if (!$__pdo) return [];
        try {
            return $__pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }
}
if (!function_exists('WriteTextFile')) {
    function WriteTextFile(string $path, string $content): bool {
        return @file_put_contents($path, $content) !== false;
    }
}

if (!function_exists('execSql')) {
    /**
     * v6 호환 — 단일/멀티 SQL 실행.
     *
     *  execSql("INSERT INTO t (a) VALUES (?)", ['v']);     // 단일 + 바인딩
     *  execSql("UPDATE a ...; UPDATE b ...; CALL p();");   // 멀티 (세미콜론 구분)
     *
     * v7 환경 PDO 는 ATTR_EMULATE_PREPARES=false 라 multi-statement 가 막혀있음.
     * 이 함수는 SQL 을 (quoted-string aware) ';' 로 split 후 각 문장을 개별 실행.
     *
     * 반환: ['resultCode'=>'success'|'fail', 'resultMessage', 'lastInsertId', 'rowCount']
     */
    function execSql(string $sql, array $bindings = []): array {
        global $__pdo;
        if (!$__pdo) {
            return ['resultCode' => 'fail', 'resultMessage' => 'PDO 미설정', 'lastInsertId' => 0, 'rowCount' => 0];
        }
        try {
            // 단일 + 바인딩 — split 안 함
            if (!empty($bindings)) {
                $stmt = $__pdo->prepare($sql);
                $stmt->execute($bindings);
                $rowCount = $stmt->rowCount();
                $lastId   = $__pdo->lastInsertId();
            } else {
                // 멀티 statement → quoted-string 인식하며 ';' 로 split, 각각 실행
                $statements = _execSql_splitStatements($sql);
                $rowCount = 0;
                foreach ($statements as $s) {
                    $s = trim($s);
                    if ($s === '') continue;
                    $r = $__pdo->exec($s);
                    if ($r !== false) $rowCount += (int)$r;
                }
                $lastId = $__pdo->lastInsertId();
            }
            if (isset($GLOBALS['_execSql_log'])) {
                $GLOBALS['_execSql_log'][] = ['sql' => $sql, 'bindings' => $bindings];
            }
            return [
                'resultCode'   => 'success',
                'resultMessage'=> 'OK',
                'lastInsertId' => (int)$lastId,
                'rowCount'     => (int)$rowCount,
            ];
        } catch (\Throwable $e) {
            error_log('[execSql] ' . $e->getMessage() . ' | SQL: ' . substr($sql, 0, 500));
            return [
                'resultCode'   => 'fail',
                'resultMessage'=> $e->getMessage(),
                'lastInsertId' => 0,
                'rowCount'     => 0,
            ];
        }
    }

    /**
     * SQL 을 quoted-string 안의 ';' 는 무시하고 statement 단위로 분할.
     * 지원: '...' 문자열 ('' 이스케이프 / \\' 이스케이프), -- 라인 주석, /* ... *\/ 블록 주석
     */
    function _execSql_splitStatements(string $sql): array {
        $stmts = [];
        $buf   = '';
        $len   = strlen($sql);
        $inSingle = false; $inDouble = false; $inLineComment = false; $inBlockComment = false;
        for ($i = 0; $i < $len; $i++) {
            $c    = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inLineComment) {
                $buf .= $c;
                if ($c === "\n") $inLineComment = false;
                continue;
            }
            if ($inBlockComment) {
                $buf .= $c;
                if ($c === '*' && $next === '/') { $buf .= $next; $i++; $inBlockComment = false; }
                continue;
            }
            if ($inSingle) {
                $buf .= $c;
                if ($c === '\\' && $next !== '') { $buf .= $next; $i++; continue; } // 백슬래시 이스케이프
                if ($c === "'" && $next === "'") { $buf .= $next; $i++; continue; } // '' 이스케이프
                if ($c === "'") { $inSingle = false; }
                continue;
            }
            if ($inDouble) {
                $buf .= $c;
                if ($c === '\\' && $next !== '') { $buf .= $next; $i++; continue; }
                if ($c === '"' && $next === '"') { $buf .= $next; $i++; continue; }
                if ($c === '"') { $inDouble = false; }
                continue;
            }

            // 일반 컨텍스트
            if ($c === '-' && $next === '-') { $inLineComment = true; $buf .= $c; continue; }
            if ($c === '/' && $next === '*') { $inBlockComment = true; $buf .= $c; continue; }
            if ($c === "'") { $inSingle = true; $buf .= $c; continue; }
            if ($c === '"') { $inDouble = true; $buf .= $c; continue; }
            if ($c === ';') { $stmts[] = $buf; $buf = ''; continue; }
            $buf .= $c;
        }
        if (trim($buf) !== '') $stmts[] = $buf;
        return $stmts;
    }
}

if (!function_exists('sqlValueReplace')) {
    // v6: SQL 값에 들어가는 문자열의 작은따옴표 escape (단순 ' → '' 변환)
    function sqlValueReplace(?string $s): string {
        return str_replace("'", "''", (string)$s);
    }
}
