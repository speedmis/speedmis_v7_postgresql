<?php
/**
 * 고객사 전용 공통 로직
 *
 * 이 파일은 SpeedMIS 업데이트 시 덮어쓰지 않습니다.
 * 고객사별 공통 로직을 여기에 작성하세요.
 *
 * 함수명 규칙: user_ + 훅이름
 *   user_pageLoad()           → common_pageLoad() 후, 개별 pageLoad() 전에 실행
 *   user_before_query()       → common_before_query() 후, 개별 before_query() 전에 실행
 *   user_save_updateAfter()   → common_save_updateAfter() 후, 개별 save_updateAfter() 전에 실행
 *   ... (모든 훅에 대해 user_ 접두어 사용 가능)
 *
 * 실행 순서: common_ → user_ → 개별
 *
 * 일반 헬퍼 함수도 자유롭게 정의 가능 (모든 프로그램에서 호출 가능)
 */

// ── 헬퍼 함수 예시 ──

// function getMyCompanyName() {
//     global $__pdo;
//     return $__pdo->query("SELECT company_name FROM my_config LIMIT 1")->fetchColumn() ?: '우리회사';
// }

// ── 훅 예시 ──

// function user_pageLoad() {
//     global $misSessionUserId;
//     // 고객사 공통: 모든 프로그램 접속 시 처리
// }

// function user_save_deleteBefore($idx, &$cancelDelete) {
//     global $misSessionIsAdmin;
//     // 고객사 정책: 관리자만 삭제 허용
//     // if ($misSessionIsAdmin !== 'Y') {
//     //     $cancelDelete = true;
//     //     $GLOBALS['_client_alert'] = '삭제 권한이 없습니다.';
//     // }
// }

/**
 * v6 misMenuList_change 포팅 — 6083 系 (g5_shop_item) 메뉴들의 base_filter / 필드 속성 동적 변경.
 *
 * 적용 대상 (RealPid):
 *   - base_filter 추가:    6084, 6085, 6086, 6094, 6095, 6096, 6123, 6124, 6129
 *   - 협력사(it_10) 필터:  6074, 6094, 6095, 6096
 *   - 일괄변경(6121):      필터 컨트롤(grid_is_handle) 변경
 *   - 6106:                it_name col_width=20
 *   - orderby part_union_level 포함 시: 정렬 컨텍스트별 컬럼 폭 조정
 */
function user_before_query(&$menu, &$fields, &$params) {
    global $__pdo, $misSessionUserId;

    $realPid = (string)($menu['real_pid'] ?? '');
    if (!str_starts_with($realPid, 'carparts')) return; // carparts 만 대상

    $bf = trim($menu['base_filter'] ?? '');
    $extra = '';

    // ──────────────────────────────────────────────────────────────────
    // ① v6 시절의 프로그램별 base_filter 추가 로직은 v7 마이그레이션에서
    //    `mis_menus.base_filter` 컬럼에 직접 저장되도록 이전됨 (6084,6085,6086,
    //    6094,6095,6096,6123,6124,6129 등). 코드에서 누적하면 DB 의 동일조건과
    //    중복 AND 로 묶여 WHERE 가 2배로 늘어나는 버그가 발생하므로 제거.
    //    새 program 별 정적 base_filter 는 DB 에 직접 넣을 것.
    // ──────────────────────────────────────────────────────────────────

    // ──────────────────────────────────────────────────────────────────
    // ② 협력사 필터 — 6074, 6094, 6095, 6096
    //    현재 사용자의 station_name 으로 it_10 (판매처/거래처) 한정 + 창고 트리 제한
    // ──────────────────────────────────────────────────────────────────
    if (in_array($realPid, ['carparts006074','carparts006094','carparts006095','carparts006096'], true)) {
        $st = $__pdo->prepare(
            "SELECT IFNULL(s.station_name,'')
               FROM mis_users u LEFT JOIN mis_stations s ON s.idx = u.station_idx
              WHERE u.user_id = ? LIMIT 1"
        );
        $st->execute([$misSessionUserId]);
        $stationName = (string)$st->fetchColumn();
        // 글로벌 admin(gadmin) 은 'admin' 협력사로 진입 (v6 호환)
        if ($stationName === 'gadmin') $stationName = 'admin';

        if ($stationName !== '') {
            $extra .= " and table_m.it_10='" . addslashes($stationName) . "'";

            // 창고 제한 — 협력사의 최상위 fixgubun prefix 로 제한
            $st2 = $__pdo->prepare('SELECT fixgubun FROM parts_storage WHERE upidx = 1 AND storage_name = ? LIMIT 1');
            $st2->execute([$stationName]);
            $fixgubun = (string)$st2->fetchColumn();
            if ($fixgubun !== '') {
                $fixgubunEsc = addslashes($fixgubun);
                foreach ($fields as &$f) {
                    if (($f['alias_name'] ?? '') === 'ca_storage_id') {
                        $f['prime_key'] = "full_name#v_parts_storage_tree#g4name!autogubun#fixgubun#@outer_tbname.fixgubun like '{$fixgubunEsc}%'";
                        break;
                    }
                }
                unset($f);
            }
        }
    }

    // base_filter 누적 적용
    if ($extra !== '') {
        $sep = ($bf === '' || str_ends_with(rtrim($bf), 'and') || str_ends_with(rtrim($bf), 'AND')) ? ' ' : ' ';
        $menu['base_filter'] = $bf . $sep . $extra;
    }

    // ──────────────────────────────────────────────────────────────────
    // ③ 일괄변경(6121) — 필터(grid_is_handle) 설정
    //    s = 상단 select 박스, t = 상단 텍스트 입력 (v6 Grid_IsHandle 호환)
    // ──────────────────────────────────────────────────────────────────
    if ($realPid === 'carparts006121') {
        $aliasesS = [
            'table_ca_storage_id1Qnfull_name',
            'table_ca_storage_id2Qnfull_name',
            'table_ca_car_idQnfull_name',
            'table_ca_idQnfull_name',
            'table_ca_storage_idQnfull_name',
            'qq_into_it_10',
        ];
        $aliasesT = ['qq_into_it_stock_qty', 'qq_into_it_price'];
        foreach ($fields as &$f) {
            $a = $f['alias_name'] ?? '';
            if (in_array($a, $aliasesS, true)) $f['grid_is_handle'] = 's';
            if (in_array($a, $aliasesT, true)) $f['grid_is_handle'] = 't';
        }
        unset($f);
    }

    // ──────────────────────────────────────────────────────────────────
    // ④ 6106 — it_name 컬럼폭 20
    // ──────────────────────────────────────────────────────────────────
    if ($realPid === 'carparts006106') {
        foreach ($fields as &$f) {
            if (($f['alias_name'] ?? '') === 'it_name') $f['col_width'] = '20';
        }
        unset($f);
    }

    // ──────────────────────────────────────────────────────────────────
    // ⑤ orderby 에 part_union_level 포함 시 컬럼 폭 동적 조정
    //    (Assy 정렬 모드일 때 차종/Qq_malls_YN 숨김, sort_num 노출)
    //    그 외엔 qq_assy_img 숨김.
    // ──────────────────────────────────────────────────────────────────
    $orderby = (string)($params['orderby'] ?? '');
    $hasUnionLevel = strpos($orderby, 'part_union_level') !== false;

    foreach ($fields as &$f) {
        $a = $f['alias_name'] ?? '';
        if ($hasUnionLevel) {
            if ($a === 'table_ca_car_idQnfull_name')      $f['col_width'] = '0';
            if ($a === 'qq_malls_YN')                     $f['col_width'] = '0';
            if ($a === 'assy_define_idxQnsort_num')       $f['col_width'] = '10';
        } else {
            if ($a === 'qq_assy_img')                     $f['col_width'] = '-1';
        }
    }
    unset($f);
}
