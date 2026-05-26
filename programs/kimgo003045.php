<?php
/**
 * kimgo003045 — 자유필드 동적 매핑 (스타일에 따라 mis_menu_fields 의 freeField01~ alias_name/타입 동적 설정)
 *
 * V6 → V7 포팅 (2026-05):
 *   - V6 외부 SQL (T-SQL) → V7 MariaDB SQL 변환
 *   - 컬럼명 PascalCase → snake_case
 *   - 테이블 MisMenuList_Detail → mis_menu_fields, kimgoStyle_detail → kimgo_style_detail
 *   - dbo.formatnums(N,'00') → LPAD(N, 2, '0')
 *   - + 문자열연결 → CONCAT
 *   - ROW_NUMBER() OVER (...) → MariaDB 10+ 윈도우 함수 지원
 *   - parent_idx / RealPid 변수는 V7 framework 가 V6 명도 자동 제공
 */

// ─────────────────────────────────────────────────────────────────────────
// 페이지 진입 시 자유필드 동적 설정 SQL — kimgo_style_detail 의 정의를
// 현재 페이지의 mis_menu_fields(freeField%) 에 매핑.
// V7 MariaDB 기준 변환:
// ─────────────────────────────────────────────────────────────────────────
$sql = "
WITH ranked_style AS (
    SELECT k.sort_element, k.midx,
           ROW_NUMBER() OVER (ORDER BY k.idx) AS row_num
    FROM kimgo_style_detail k
    WHERE k.midx = (SELECT style_idx FROM kimgo_main_list WHERE idx = ?)
)
UPDATE mis_menu_fields m
JOIN kimgo_style_detail k ON (m.sort_order - 2) = k.sort_element
JOIN ranked_style r ON r.sort_element = k.sort_element AND k.midx = r.midx
SET m.alias_name = CONCAT('freeField', LPAD(r.row_num, 2, '0')),
    m.col_title = k.grid_columns_title,
    m.col_width = k.grid_columns_width,
    m.db_field = CONCAT('freeField', LPAD(r.row_num, 2, '0')),
    m.schema_type = k.grid_schema_type,
    m.items = k.grid_items,
    m.schema_validation = k.grid_schema_validation,
    m.grid_align = k.grid_align,
    m.max_length = k.grid_max_length,
    m.default_value = k.grid_default,
    m.grid_ctl_name = k.grid_ctl_name,
    m.grid_view_class = CASE WHEN k.grid_ctl_name='textarea' THEN 'col-xs-6 row-3' ELSE 'col-xs-6 row-1' END
WHERE m.real_pid = 'kimgo003045'
  AND m.db_field LIKE 'freeField%';
";
// V7 의 execSql 은 prepared statement 지원 — parent_idx 바인딩
if (!empty($GLOBALS['parent_idx'] ?? '')) {
    execSql($sql, [(int)$GLOBALS['parent_idx']]);
}

function pageLoad()
{
    global $actionFlag, $idx, $parent_idx, $real_pid, $misSessionIsAdmin;

    // 부모 (kimgo_main_list) 의 style_idx → kimgo_style 의 styleName 조회 → 탭 라벨로 사용
    $title = '';
    if (!empty($parent_idx)) {
        $title = (string)onlyOnereturnSql(
            "SELECT (SELECT style_name FROM kimgo_style WHERE idx = style_idx)
               FROM kimgo_main_list WHERE idx = " . (int)$parent_idx
        );
    }

    // CSS — 보존하되 V7 마크업 불일치는 비활성 (xdisplay 는 의도적 비활성)
    $GLOBALS['_client_css'] = '
.k-tabstrip-top > .k-tabstrip-items-wrapper { display: none; }
';

    /*
      [V6 원본 동작 요약]
        - viewLogic_afterLoad: parent.$(\"li[tabrealpid='kimgo003045']\") 의 라벨을 동적 title 로 교체
          + freeField01 의 라벨이 '스타일정의' 일 경우 탭 숨기고 다른 탭으로 자동 이동
        → V7 권장: view_load(&$row) 에서 _client_buttons 또는 _client_redirect 로 처리

        - rowFunction_UserDefine, rowFunctionAfter_UserDefine, listLogic_*, viewLogic_afterLoad_continue,
          viewLogic_afterLoad_viewPrint — 모두 주석/빈 함수
    */
}
