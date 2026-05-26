<?php
/**
 * 웹소스관리 디테일 — 267번 프로그램 훅
 * - INFORMATION_SCHEMA.COLUMNS JOIN 에 TABLE_SCHEMA 조건 추가
 * - 툴바에 '자동정렬' 버튼 추가 — sort_order 재번호 + real_pid_alias_name 갱신
 * - 특정 컬럼(alias_name/col_title/db_field/db_table/sort_order) 편집 시 동일 로직 자동실행
 */

// [DEBUG] 파일 로드 확인용 — opcache 가 최신 버전을 읽는지 검증
if (!defined('P267_LOG')) define('P267_LOG', __DIR__ . '/../logs/267_edit_debug.log');
@file_put_contents(P267_LOG,
    date('[Y-m-d H:i:s]') . " 267 file LOADED (act=" . ($_GET['act'] ?? '?') . " idx=" . ($_GET['idx'] ?? '?') . ")\n",
    FILE_APPEND);

function _addTableSchema(string &$sql): void {
    $dbName = $_ENV['DB_NAME'] ?? 'speedmis_v7';
    if (!str_contains($sql, 'TABLE_SCHEMA')) {
        $sql = str_replace(
            'table_COLUMNS.TABLE_NAME=',
            "table_COLUMNS.TABLE_SCHEMA='{$dbName}' AND table_COLUMNS.TABLE_NAME=",
            $sql
        );
    }
}

function list_query(&$selectQuery, &$countQuery) {
    _addTableSchema($selectQuery);
    _addTableSchema($countQuery);
}

function view_query(&$viewSql) {
    // INFORMATION_SCHEMA.COLUMNS JOIN 통째로 제거 — TABLE_SCHEMA 필터를 줘도 dictionary scan
    // 자체가 ~100ms 라 효과 없음 (벤치마크 결과). JOIN 빼면 4ms 로 떨어짐.
    // 컬럼 메타(DATA_TYPE/길이 등) 는 view_load 에서 literal 값으로 별도 SELECT (~7ms).
    // 정규식 /i — alias 가 'table_columns' 소문자/'table_COLUMNS' 혼합 어느 쪽이든 매치.
    $viewSql = preg_replace(
        '/LEFT JOIN INFORMATION_SCHEMA\.COLUMNS\s+\S+\s+ON[^W]+(?=WHERE|LEFT|$)/i',
        '',
        $viewSql
    );
    $viewSql = preg_replace('/table_COLUMNS\.\w+/i', "''", $viewSql);
}

// view 결과 row 에 컬럼 메타정보 보강 — view_query 에서 INFORMATION_SCHEMA JOIN 제거한 대신
// 여기서 1) real_pid → mis_menus.table_name 룩업, 2) literal TABLE_SCHEMA/TABLE_NAME/COLUMN_NAME 으로
// INFORMATION_SCHEMA.COLUMNS 단일행 조회. 합쳐서 ~12ms (vs 기존 107ms).
function view_load(&$row) {
    if (!is_array($row)) return;
    global $__pdo;
    if (!($__pdo instanceof \PDO)) return;

    $rp  = trim((string)($row['real_pid'] ?? ''));
    $col = trim((string)($row['db_field'] ?? ''));
    if ($rp === '' || $col === '') return;
    // db_field 가 'table_m.col_title' 같은 표현식이면 컬럼명만 추출 (table_m. 접두어 제거)
    if (str_contains($col, '.')) $col = substr($col, strrpos($col, '.') + 1);
    if ($col === '' || preg_match('/[\s(\'"]/', $col)) return;  // 복합식이면 skip

    try {
        $stmt = $__pdo->prepare("SELECT table_name FROM mis_menus WHERE real_pid = ? LIMIT 1");
        $stmt->execute([$rp]);
        $tname = trim((string)$stmt->fetchColumn());
        if ($tname === '') return;

        // 'g5_db.g5_xxx' 형태면 스키마/테이블 분리, 아니면 현재 DB
        $schema = $_ENV['DB_NAME'] ?? '';
        if (str_contains($tname, '.')) {
            [$schema, $tname] = explode('.', $tname, 2);
        }

        $stmt = $__pdo->prepare(
            "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_DEFAULT, IS_NULLABLE
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $stmt->execute([$schema, $tname, $col]);
        $meta = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($meta) {
            $row['table_COLUMNSQndata_type']                = $meta['DATA_TYPE'] ?? '';
            $row['table_COLUMNSQncharacter_maximum_length'] = $meta['CHARACTER_MAXIMUM_LENGTH'] ?? '';
            $row['table_COLUMNSQncolumn_default']           = $meta['COLUMN_DEFAULT'] ?? '';
            $row['table_COLUMNSQnis_nullable']              = $meta['IS_NULLABLE'] ?? '';
        }
    } catch (\Throwable) {}
}

// parent_idx (숫자 idx 또는 real_pid 문자열) → real_pid 로 정규화
// $parent_idx 는 서버에서 (int) 로 캐스팅되어 'carparts006083' 같은 문자열이 0 으로 변함 → 원본 GET 도 확인
function _p267_resolveParentRealPid($parentIdx): string {
    $raw = trim((string)($_GET['parent_idx'] ?? ''));
    if ($raw === '' && ($parentIdx !== '' && $parentIdx !== null && $parentIdx !== 0)) {
        $raw = (string)$parentIdx;
    }
    if ($raw === '') return '';
    if (is_numeric($raw)) {
        $rp = (string)onlyOnereturnSql("SELECT real_pid FROM mis_menus WHERE idx = " . (int)$raw);
        if ($rp !== '') return $rp;
    }
    return $raw;
}

// v6 자동정렬 MySQL 로직 포팅 — sort_order 재번호 + real_pid_alias_name 채움
function _p267_autoSortForRealPid(string $realPid): bool {
    if ($realPid === '') return false;
    $rpEsc = str_replace("'", "''", $realPid);
    if (class_exists('\App\Config\Database') && \App\Config\Database::isPg()) {
        // PG: window function 으로 sort_order 재번호 (MariaDB 의 user variable + ORDER BY UPDATE 대체)
        $sql = "
            WITH renumbered AS (
                SELECT idx, ROW_NUMBER() OVER (ORDER BY sort_order, idx) AS new_sort
                  FROM mis_menu_fields
                 WHERE real_pid = '{$rpEsc}'
            )
            UPDATE mis_menu_fields m
               SET sort_order = r.new_sort,
                   real_pid_alias_name = m.real_pid || '.' || m.alias_name
              FROM renumbered r
             WHERE m.idx = r.idx;

            UPDATE mis_menu_fields
               SET real_pid_alias_name = real_pid || '.' || alias_name
             WHERE real_pid IN (SELECT real_pid FROM mis_menus WHERE useflag = '1')
               AND alias_name <> ''
               AND (COALESCE(real_pid_alias_name, '') = '' OR RIGHT(real_pid_alias_name, 1) = '.');
        ";
    } else {
        $sql = "
            SET @sortNum := 0;
            UPDATE mis_menu_fields
               SET sort_order = (@sortNum := @sortNum + 1),
                   real_pid_alias_name = CONCAT(real_pid, '.', alias_name)
             WHERE real_pid = '{$rpEsc}'
             ORDER BY sort_order, idx;

            UPDATE mis_menu_fields
               SET real_pid_alias_name = CONCAT(real_pid, '.', alias_name)
             WHERE real_pid IN (SELECT real_pid FROM mis_menus WHERE useflag = '1')
               AND alias_name <> ''
               AND (IFNULL(real_pid_alias_name, '') = '' OR RIGHT(real_pid_alias_name, 1) = '.');
        ";
    }
    $r = execSql($sql);
    return ($r['resultCode'] ?? '') === 'success';
}

function pageLoad() {
    global $actionFlag;
    if ($actionFlag === 'list') {
        // 사용자 정의 버튼 — 툴바에 '자동정렬' 추가 (list_json_init 에서 action 처리)
        $GLOBALS['_client_buttons'] = [
            ['label' => '자동정렬', 'action' => '자동정렬'],
        ];
    }
}

// 목록 SELECT 직전 훅 — 자동정렬 버튼 클릭 시 UPDATE 실행
// (프레임워크가 list_json_init 을 v6 와 동일하게 SELECT 전에 실행하도록 수정됨)
function list_json_init() {
    global $actionFlag, $customAction, $parent_idx, $__pdo;

    if (($actionFlag ?? '') !== 'list') return;
    if (($customAction ?? '') !== '자동정렬') return;

    $realPid = _p267_resolveParentRealPid($parent_idx);
    if ($realPid === '') {
        $GLOBALS['_client_toast'] = '상위 real_pid 를 찾을 수 없습니다.';
        return;
    }

    // 1) alias_name 재생성
    $aliasCount = 0;
    if ($__pdo instanceof \PDO) {
        require_once __DIR__ . '/../migration/alias_fix.php';
        if (function_exists('aliasFixForRealPids')) {
            try { $aliasCount = aliasFixForRealPids($__pdo, [$realPid]); } catch (\Throwable $e) {}
        }
    }
    // 2) sort_order + real_pid_alias_name 동기화
    if (_p267_autoSortForRealPid($realPid)) {
        $GLOBALS['_client_toast'] = "자동정렬이 완료되었습니다. (alias_name {$aliasCount}건 갱신)";
    } else {
        $GLOBALS['_client_toast'] = '처리가 실패되었습니다.';
    }
}

// 인라인 편집에서 정렬/필드 정의가 바뀐 경우:
//   1) alias_name 재생성 (migration/alias_fix.php 의 aliasFixForRealPids 호출)
//   2) sort_order 재번호 + real_pid_alias_name 갱신
function save_updateAfter($idx, &$afterScript) {
    global $isListEdit, $listEditField, $parent_idx, $__pdo;

    $log = defined('P267_LOG') ? P267_LOG : (__DIR__ . '/../logs/267_edit_debug.log');
    @file_put_contents($log, date('[Y-m-d H:i:s]') . " save_updateAfter idx={$idx} isListEdit=" . var_export($isListEdit, true) . " listEditField=" . json_encode($listEditField ?? null) . " parent_idx=" . var_export($parent_idx, true) . "\n", FILE_APPEND);

    if (!$isListEdit || empty($listEditField)) {
        @file_put_contents($log, "  → skip (not list edit or empty)\n", FILE_APPEND);
        return;
    }

    $triggers = ['alias_name', 'col_title', 'db_field', 'db_table', 'sort_order'];
    $hit = false;
    foreach ((array)$listEditField as $f) {
        if (in_array($f, $triggers, true)) { $hit = true; break; }
    }
    if (!$hit) {
        @file_put_contents($log, "  → skip (no trigger field)\n", FILE_APPEND);
        return;
    }

    $realPid = _p267_resolveParentRealPid($parent_idx);
    // 인라인 편집 시엔 parent_idx 가 없을 수 있음 → 편집 대상 행의 real_pid 직접 조회
    if ($realPid === '' && (int)$idx > 0) {
        $realPid = (string)onlyOnereturnSql("SELECT real_pid FROM mis_menu_fields WHERE idx = " . (int)$idx);
    }
    @file_put_contents($log, "  → resolved realPid='{$realPid}'\n", FILE_APPEND);
    if ($realPid === '') return;

    // 1) alias_name 재생성 (한글 로마자 변환 등)
    if ($__pdo instanceof \PDO) {
        require_once __DIR__ . '/../migration/alias_fix.php';
        if (function_exists('aliasFixForRealPids')) {
            try {
                $n = aliasFixForRealPids($__pdo, [$realPid]);
                @file_put_contents($log, "  → aliasFixForRealPids updated {$n} rows\n", FILE_APPEND);
            } catch (\Throwable $e) {
                @file_put_contents($log, "  → aliasFix EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        } else {
            @file_put_contents($log, "  → aliasFixForRealPids function not found\n", FILE_APPEND);
        }
    } else {
        @file_put_contents($log, "  → \$__pdo not available\n", FILE_APPEND);
    }
    // 2) sort_order + real_pid_alias_name 동기화
    _p267_autoSortForRealPid($realPid);
    @file_put_contents($log, "  → autoSort done\n", FILE_APPEND);

    // 클라이언트에게 "이 행을 재조회해서 UI 에 반영하라" 신호
    $GLOBALS['_listEditReload'] = true;
}