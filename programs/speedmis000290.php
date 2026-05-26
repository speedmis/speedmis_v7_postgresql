<?php

function list_query(&$selectQuery, &$countQuery) {
    // 목록에서 INFORMATION_SCHEMA JOIN 제거 (672ms → 5ms)
    // 컬럼 타입 정보는 상세보기(267)에서 확인
    $selectQuery = preg_replace(
        '/LEFT JOIN INFORMATION_SCHEMA\.COLUMNS\s+\S+\s+ON[^W]+(?=WHERE|LEFT|$)/i',
        '',
        $selectQuery
    );
    // SELECT 에 남은 table_columns.XXX 참조 → 빈값으로 교체.
    // /i — group_compute 의 alias 가 'table_columns' 소문자로 정의되어 있고, mis_menu_fields.db_table 은
    // 'table_COLUMNS' 혼합 표기인 경우 buildSelectFromFields 의 alias 정규화로 SELECT 식이 소문자로 떨어짐.
    $selectQuery = preg_replace('/table_COLUMNS\.\w+/i', "''", $selectQuery);

    // COUNT 에서도 제거
    $countQuery = preg_replace(
        '/LEFT JOIN INFORMATION_SCHEMA\.COLUMNS[^W]+(?=WHERE|LEFT|$)/i',
        '',
        $countQuery
    );
}

function list_json_init() {
    global $__pdo;
    // 프로그램ID 컬럼 __html 생성 시 필요한 menu_type 을 real_pid 기준으로 사전 수집
    if ($__pdo) {
        $rows = $__pdo->query("SELECT real_pid, menu_type FROM mis_menus WHERE useflag='1'")
                      ->fetchAll(\PDO::FETCH_KEY_PAIR);
        $GLOBALS['_290_menuTypes'] = $rows ?: [];
    }
}

// 통합 버튼 훅 — 프로그램ID 셀에 [연결] [소스] 버튼 (list/view 공유)
function row_buttons(&$row, array &$buttons): void {
    $rp = (string)($row['table_real_pidQnreal_pid'] ?? '');
    if ($rp === '') return;

    $rpEsc = htmlspecialchars($rp, ENT_QUOTES, 'UTF-8');

    // 연결 — 해당 프로그램 새 탭으로 열기
    $buttons['table_real_pidQnidx'][] =
        '<span class="btn-open" data-opentab=\'{"realPid":"' . $rpEsc . '"}\'>연결</span>';

    // 소스 — 업무용MIS (menu_type='01') 만 노출, 266 으로 진입
    $types = $GLOBALS['_290_menuTypes'] ?? [];
    if (($types[$rp] ?? '') === '01') {
        $buttons['table_real_pidQnidx'][] =
            '<span class="btn-open" data-opentab=\'{"gubun":266,"idx":"' . $rpEsc . '"}\'>소스</span>';
    }
}
