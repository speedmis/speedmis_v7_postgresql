<?php
/**
 * kimgo003052 — 업무관리 (장기계획 / 파생업무 트리)
 *
 * V6 → V7 포팅 (2026-05):
 *   - 본 파일은 V6 중에서 가장 V7 친화적 (이미 list_query, list_json_init, save_writeQueryBefore,
 *     save_deleteBefore 등 V7 표준 hook 명을 사용). 함수명 그대로 두고 SQL/컬럼명만 변환.
 *   - V6 misMenuList0_change → list_json_init 으로 통합
 *   - V6 misMenuList_change → before_query 로 변환
 *   - V6 jQuery onlyList → $GLOBALS['_onlyList'] = true
 */

// 기본 설정
$list_numbering = ''; // 리스트 No. 순번 항목 제거 (V7 framework 호환)


function pageLoad()
{
    global $actionFlag, $idx, $parent_idx, $gubun, $real_pid, $misSessionIsAdmin, $misSessionUserId;

    // V6 의 onlyList — 등록/수정/삭제 버튼 숨김
    $GLOBALS['_onlyList'] = true;

    // V6 CSS — V7 마크업과 일부 불일치(round_*) 가 있어 효과 제한적이지만 보존
    $GLOBALS['_client_css'] = '
div.before_round_depG1, div.before_round_comG1 { display: none; }
.k-tabstrip-items-wrapper { display: none !important; }
div.td_div { max-height: max-content; text-overflow: ellipsis; overflow: hidden; overflow-wrap: normal; }
';
}


/**
 * V6 misMenuList0_change → list_json_init
 *  + 추가: virtual_fieldQnaddDep 필터에 부서 트리에서 INSERT (대량 작업 마법)
 */
function list_json_init()
{
    global $actionFlag, $parent_idx, $misSessionUserId;
    global $appSql; // V6 호환 — 실제로는 직접 SQL 실행

    // V6 의 isThisChild / psize=5 신호 (V7 에서는 영향 미미)
    if ($actionFlag !== 'list' && !empty($parent_idx)) {
        $GLOBALS['_client_isThisChild'] = 'Y';
    }

    // 부서 자동 입력: filter 에 toolbar_virtual_fieldQnaddDep 가 포함되면
    // 해당 station_idx 의 트리 정보를 kimgo_main_list 에 INSERT
    $allFilter = (string)($_GET['allFilter'] ?? '[]');
    if (str_contains($allFilter, 'toolbar_virtual_fieldQnaddDep') && preg_match(
            '/toolbar_virtual_fieldQnaddDep["\']?\s*[:,]\s*["\']?(?:value["\']?\s*:\s*["\'])?(\d+)/',
            $allFilter, $m)) {
        $stationIdx = (int)$m[1];
        if ($stationIdx > 0) {
            // V7 변환: vMisStationTree → mis_stations 트리 (g4num/g8num/g12num 은 sort 컬럼들로 간주)
            // 원본 SQL 의 g4num, g8num, g12num 매핑은 사이트 비즈니스 로직이라 임시 매핑
            execSql(
                "INSERT INTO kimgo_main_list (dep_g1, dep_g2, dep_g3, wdater)
                 SELECT idx, sort_g8, sort_g10, ?
                   FROM mis_stations WHERE idx = ?",
                [(string)$misSessionUserId, $stationIdx]
            );
        }
    }
}


/**
 * V6 misMenuList_change → before_query
 *   - 다른 real_pid (kimgo003054) 의 isEnd 컬럼을 숨김 (col_width=-1)
 *   - 동적 필드 가시성 조정
 */
function before_query(&$menu, $fields, $params)
{
    if (($menu['real_pid'] ?? '') === 'kimgo003054') {
        foreach ($fields as &$f) {
            if (($f['alias_name'] ?? '') === 'isEnd') {
                $f['col_width'] = -1;
            }
        }
        unset($f);
    }
}


/**
 * V6 list_query → V7 list_query (이미 V7 표준명)
 *   - virtual_fieldQnaddDep 의 dropdown 검색 시 부서 트리 SELECT 변형
 *   - T-SQL `top 100`, `concat`, `len`, `case when ... then ... else ... end` → MariaDB 호환
 */
function list_query(&$selectQuery, &$countQuery)
{
    $flag    = (string)($_GET['flag']    ?? '');
    $selField = (string)($_GET['selField'] ?? '');

    if ($flag === 'toolbar_searchField_kendoDropDownList' && $selField === 'virtual_fieldQnaddDep') {
        $selectQuery = "
SELECT virtual_fieldQnaddDep FROM (
  SELECT DISTINCT 1 AS nnn,
    CONCAT(idx, ' | ', station_name,
      CASE WHEN CHAR_LENGTH(autogubun) >= 8 THEN ' > ' ELSE '' END,
      IFNULL(parent_station_name, ''),
      CASE WHEN CHAR_LENGTH(autogubun) >= 12 THEN ' > ' ELSE '' END,
      IFNULL(grandparent_station_name, '')
    ) AS virtual_fieldQnaddDep
  FROM mis_stations table_m
) aaa
ORDER BY nnn, virtual_fieldQnaddDep
LIMIT 100
";
    }
}


/**
 * V6 save_writeQueryBefore → V7 동일 명 (parameter signature 약간 다름)
 *   - INSERT 후 gidx 가 0 이면 새 idx 로 자동 설정
 */
function save_writeQueryBefore(&$sql, &$bindings)
{
    // V7: $sql 변경 후 별도로 $sql_next (post-INSERT) 실행은 save_writeAfter 훅에서
    // 여기선 INSERT 자체는 그대로, post-INSERT 동작은 save_writeAfter 에서 수행
}

function save_writeAfter($newIdx, &$afterScript)
{
    // V6: update kimgo_main_list set gidx=$newIdx where idx=$newIdx and gidx=0
    if ((int)$newIdx > 0) {
        execSql(
            "UPDATE kimgo_main_list SET gidx = ? WHERE idx = ? AND (gidx = 0 OR gidx IS NULL)",
            [(int)$newIdx, (int)$newIdx]
        );
    }
}


/**
 * V6 save_deleteBefore → V7 동일 명
 *   - 기준업무(gidx) 보다 파생업무를 먼저 삭제하도록 검증
 */
function save_deleteBefore($idx, &$cancelDelete)
{
    $idxInt = (int)$idx;
    if ($idxInt <= 0) return;

    $cnt1 = (int)onlyOnereturnSql(
        "SELECT COUNT(*) FROM kimgo_main_list WHERE useflag = 1 AND gidx = " . $idxInt
    );
    $cnt2 = (int)onlyOnereturnSql(
        "SELECT COUNT(*) FROM kimgo_main_list WHERE useflag = 1 AND idx  = " . $idxInt
    );

    if ($cnt1 > $cnt2) {
        $GLOBALS['_client_alert'] = '기준 업무보다 파생 업무를 먼저 삭제해야 합니다.';
        $cancelDelete = true;
    }
}


/**
 * V6 addLogic_treat → V7 동일 명
 *   - get_gidx: 특정 idx 의 부모 gidx 조회
 */
function addLogic_treat(&$result)
{
    $question = requestVB('question');
    $pIdx     = (int)requestVB('idx');

    if ($question === 'get_gidx' && $pIdx > 0) {
        $sql = "SELECT gidx FROM kimgo_main_list WHERE useflag = 1
                  AND idx = (SELECT gidx FROM kimgo_main_list WHERE idx = " . $pIdx . ")";
        $result = (string)onlyOnereturnSql($sql) ?: '';
    }
}
