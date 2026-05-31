<?php
/**
 * 웹소스관리 디테일 — 267번 프로그램 훅
 * - INFORMATION_SCHEMA.COLUMNS JOIN 에 TABLE_SCHEMA 조건 추가
 * - 툴바에 '자동정렬' 버튼 — sort_order 재번호 + alias_name 재생성
 * - 인라인 편집 트리거 (alias_name/col_title/db_field/db_table) 시 aliasFix 자동
 *   ※ sort_order 는 트리거에서 제외 — 번호 직접 수정해도 자동정렬 안 함 (자동정렬 버튼 클릭 시에만)
 * - alias 가 다른 행까지 영향줄 수 있으므로 _listFullReload 신호 → 클라이언트 전체 목록 reload
 */

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
    $viewSql = preg_replace(
        '/LEFT JOIN INFORMATION_SCHEMA\.COLUMNS\s+\S+\s+ON[^W]+(?=WHERE|LEFT|$)/i',
        '',
        $viewSql
    );
    $viewSql = preg_replace('/table_COLUMNS\.\w+/i', "''", $viewSql);
}

function view_load(&$row) {
    if (!is_array($row)) return;
    global $__pdo;
    if (!($__pdo instanceof \PDO)) return;

    $rp  = trim((string)($row['real_pid'] ?? ''));
    $col = trim((string)($row['db_field'] ?? ''));
    if ($rp === '' || $col === '') return;
    if (str_contains($col, '.')) $col = substr($col, strrpos($col, '.') + 1);
    if ($col === '' || preg_match('/[\s(\'"]/', $col)) return;

    try {
        $stmt = $__pdo->prepare("SELECT table_name FROM mis_menus WHERE real_pid = ? LIMIT 1");
        $stmt->execute([$rp]);
        $tname = trim((string)$stmt->fetchColumn());
        if ($tname === '') return;

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

function _p267_autoSortForRealPid(string $realPid): bool {
    if ($realPid === '') return false;
    $rpEsc = str_replace("'", "''", $realPid);
    if (class_exists('\App\Config\Database') && \App\Config\Database::isPg()) {
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
        $GLOBALS['_client_buttons'] = [
            ['label' => '자동정렬', 'action' => '자동정렬'],
        ];
    }
}

/**
 * 목록 SELECT 직전 훅 — '자동정렬' 버튼 클릭 시 UPDATE 실행
 */
function list_json_init() {
    global $actionFlag, $customAction, $parent_idx, $__pdo;

    if (($actionFlag ?? '') !== 'list') return;
    if (($customAction ?? '') !== '자동정렬') return;

    $realPid = _p267_resolveParentRealPid($parent_idx);
    if ($realPid === '') {
        $GLOBALS['_client_toast'] = '상위 real_pid 를 찾을 수 없습니다.';
        return;
    }

    $aliasCount = 0;
    if ($__pdo instanceof \PDO) {
        require_once __DIR__ . '/../migration/alias_fix.php';
        if (function_exists('aliasFixForRealPids')) {
            try { $aliasCount = aliasFixForRealPids($__pdo, [$realPid]); } catch (\Throwable $e) {}
        }
    }
    if (_p267_autoSortForRealPid($realPid)) {
        $GLOBALS['_client_toast'] = "자동정렬이 완료되었습니다. (alias_name {$aliasCount}건 갱신)";
    } else {
        $GLOBALS['_client_toast'] = '처리가 실패되었습니다.';
    }
}

/**
 * 인라인 편집 후 처리.
 * 트리거: alias_name / col_title / db_field / db_table 변경 시 aliasFix 실행
 * (sort_order 는 트리거에서 제외 — 직접 편집해도 자동정렬 안 함, '자동정렬' 버튼만)
 * aliasFix 가 다른 행의 alias 도 바꿀 수 있어 _listFullReload 로 전체 목록 reload 요청.
 */
function save_updateAfter($idx, &$afterScript) {
    global $isListEdit, $listEditField, $parent_idx, $__pdo;

    $log = defined('P267_LOG') ? P267_LOG : (__DIR__ . '/../logs/267_edit_debug.log');
    @file_put_contents($log, date('[Y-m-d H:i:s]') . " save_updateAfter idx={$idx} isListEdit=" . var_export($isListEdit, true) . " listEditField=" . json_encode($listEditField ?? null) . " parent_idx=" . var_export($parent_idx, true) . "\n", FILE_APPEND);

    if (!$isListEdit || empty($listEditField)) {
        @file_put_contents($log, "  → skip (not list edit or empty)\n", FILE_APPEND);
        return;
    }

    // sort_order 는 의도적으로 제외 — '자동정렬' 버튼 클릭 시에만 재번호
    $triggers = ['alias_name', 'col_title', 'db_field', 'db_table'];
    $hit = false;
    foreach ((array)$listEditField as $f) {
        if (in_array($f, $triggers, true)) { $hit = true; break; }
    }
    if (!$hit) {
        @file_put_contents($log, "  → skip (no trigger field — sort_order 직접편집은 자동정렬 안 함)\n", FILE_APPEND);
        return;
    }

    $realPid = _p267_resolveParentRealPid($parent_idx);
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
        }
    }
    // 2) real_pid_alias_name 동기화만 (sort_order 는 손대지 말 것!)
    $rpEsc = str_replace("'", "''", $realPid);
    execSql("
        UPDATE mis_menu_fields
           SET real_pid_alias_name = CONCAT(real_pid, '.', alias_name)
         WHERE real_pid = '{$rpEsc}'
           AND alias_name <> ''
    ");
    @file_put_contents($log, "  → real_pid_alias_name resync done (sort_order 손대지 않음)\n", FILE_APPEND);

    // 다른 행까지 영향받았으므로 전체 목록 reload 신호
    $GLOBALS['_listFullReload'] = true;
}
