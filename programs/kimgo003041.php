<?php
/**
 * kimgo003041 — 업무관리 (kimgo_main_list 기반)
 *
 * V6 → V7 포팅 (2026-05):
 *   - misMenuList0_change → list_json_init
 *   - misMenuList_change → before_query (base_filter 동적 추가는 행단위 list_json_load 이전에 적용해야)
 *   - 거대한 V6 jQuery/Kendo 클라이언트 JS (1100+줄) 는 V7 React 환경에 호환 안 됨 → 보존(주석) + 추후 V7 표준으로 재구현 권장
 *   - SQL 의 PascalCase 컬럼/테이블 → V7 snake_case 변환
 *   - $MisSession_UserID 등 V6 별칭은 V7 framework 가 자동 제공 (참조)
 */

/**
 * V6 misMenuList0_change → V7 list_json_init
 *  - parent_idx 있는 자식모드 진입 시 응답에 메타 추가 (V6 의 $data[0]['isThisChild'] 역할)
 *  - V7 에서는 _client_buttons 또는 _client_css / 클라이언트 메타 채널을 통해 신호
 */
function list_json_init()
{
    global $actionFlag, $parent_idx;
    if ($actionFlag !== 'list' && !empty($parent_idx)) {
        // V6: $data[0]['isThisChild'] = 'Y'; psize=5;
        // V7 측 사용자정의 신호용 — 클라이언트 메타로 전달 (대체로 onlyList 등으로 충분)
        $GLOBALS['_client_isThisChild'] = 'Y';
    }
}

/**
 * 행 단위 후처리 — qqConforms 의 ¶ 구분자를 <br> 로 치환해 그리드 셀에 멀티라인 표시.
 * (원본 SQL: concat(...,'/',...,'¶',...,'¶',...) — 첫 ¶ 와 두 번째 ¶ 가 줄바꿈 위치)
 *   결과 예: "신대종합모터스/총무 <br> 일반업무 <br> //"
 * V7 의 __html 메커니즘 사용 — raw 값은 보존, 표시만 HTML.
 */
function list_json_load(&$data)
{
    $v = (string)($data['qqConforms'] ?? '');
    if ($v === '' || strpos($v, '¶') === false) return;
    $escaped = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $data['__html']['qqConforms'] = str_replace('¶', '<br>', $escaped);
}

/**
 * V6 misMenuList_change → V7 before_query (쿼리 빌드 전 base_filter 동적 추가)
 *
 * 원본: $result[0]['g09'] (= base_filter) 에 isLong=0/1 추가
 * V7 동등 동작: $menu['base_filter'] 를 변형
 */
function before_query(&$menu, $fields, $params)
{
    if (($menu['real_pid'] ?? '') === 'kimgo003041') {
        $menu['base_filter'] = trim((string)($menu['base_filter'] ?? '')) . ' and table_m.is_long=0';
    } else {
        $menu['base_filter'] = trim((string)($menu['base_filter'] ?? '')) . ' and table_m.is_long=1';
    }
}

function pageLoad()
{
    global $actionFlag, $real_pid, $idx, $parent_idx, $misSessionUserId, $misSessionIsAdmin;

    // list 셀 클릭 시 view 가 아닌 modify 모드로 진입 — 사용자 명시 요구
    $GLOBALS['_client_alwaysModify'] = true;

    // 디자인 시스템 호환 CSS 만 추출 (V6 .k-window / .dialog-content 등은 V7 에 존재 안 함이라 효과 없음)
    $GLOBALS['_client_css'] = '
/* kimgo003041 — V6 다이얼로그 스타일 (V7 에 .k-window 가 없어 대부분 미동작이지만 원본 보존) */
.k-window.fancy-shadow { border-radius: 16px; overflow: hidden;
  box-shadow: 0 10px 30px rgba(0,0,0,0.25); background: linear-gradient(145deg,#fff,#f0f0f0); }
';

    // ─────────────────────────────────────────────────────────────────────────
    // V6 클라이언트 JS (jQuery + Kendo 의존) — V7 React 환경에서 미동작.
    // 원본 의도 보존용 PHP 주석. 추후 V7 표준 (DataGrid useImperativeHandle, _client_buttons,
    // list_json_load 의 __html 주입, view_load + 폼 메타 등) 로 재구현 권장.
    // ─────────────────────────────────────────────────────────────────────────
    /*
      [V6 원본 동작 요약]
        1. depG1 select 변경 → chbAll 자동 토글 + 부서별 권한 설정
        2. ActionFlag 가 view/modify 이고 psize<>5 면 parent_idx 와 함께 자식 그리드 모드로 redirect
        3. parent.select_list() 와 동기화하여 모달-디테일 변경 시 부모 리스트 자동 새로고침
        4. columns_templete: virtual_fieldQnwork_end 셀에 "처리완료" k-button 표시
        5. rowFunction_UserDefine / thisLogic_toolbar / listLogic_*: 빈 함수 (boilerplate)
        6. confirms() — 다이얼로그 helper (작업유형 선택)
        7. open_attach() — 첨부 파일 열기

      V7 으로의 권장 매핑:
        - depG1 변경 후처리 → DataForm 의 onChange + DataGrid reload
        - ActionFlag redirect → mis:openTab 이벤트 + addUrl 사용
        - 자동 새로고침 → window.dispatchEvent('mis:reloadGrid')
        - 셀 커스텀 출력 → list_json_load(&$data) 안에서 $data['__html'][alias] = '<a class="k-button">처리완료</a>'
        - 다이얼로그 → DataForm 의 _client_alert / _client_confirm
    */
}

/**
 * 신규 INSERT 후 gidx 가 비어있으면 자기 idx 로 자동 채움.
 * (referInsert 로 prefill 된 gidx 가 있으면 — 같은 그룹의 자식 record 로 — 그대로 유지)
 */
function save_writeAfter($newIdx, &$afterScript)
{
    global $__pdo;
    $newIdx = (int)$newIdx;
    if ($newIdx <= 0) return;
    $stmt = $__pdo->prepare(
        "UPDATE kimgo_main_list SET gidx = idx WHERE idx = ? AND IFNULL(gidx, 0) = 0"
    );
    $stmt->execute([$newIdx]);
}

/**
 * 일괄 삭제 검증 — 그룹 대표(gidx=idx) 를 단독 삭제하려는 경우 차단.
 * 같은 gidx 의 다른 자식들이 모두 함께 선택되어 있을 때만 통과.
 */
function save_bulkDeleteBefore(&$idxList, &$cancelDelete)
{
    global $__pdo;
    if (empty($idxList)) return;

    // bulk 컨텍스트 마킹 — save_deleteBefore 가 root 단독삭제 검사를 skip 하도록
    $GLOBALS['_kimgo003041_bulkCtx'] = array_map('intval', $idxList);

    $place = implode(',', array_fill(0, count($idxList), '?'));
    $stmt = $__pdo->prepare("SELECT idx, gidx FROM kimgo_main_list WHERE idx IN ($place) AND useflag='1'");
    $stmt->execute($idxList);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $idxSet = array_flip(array_map('intval', $idxList));
    foreach ($rows as $r) {
        if ((int)$r['gidx'] !== (int)$r['idx']) continue; // root 만 검사
        $g = (int)$r['gidx'];
        $cs = $__pdo->prepare("SELECT idx FROM kimgo_main_list WHERE gidx=? AND useflag='1'");
        $cs->execute([$g]);
        $allMembers = array_map('intval', $cs->fetchAll(\PDO::FETCH_COLUMN));
        $missing = array_diff($allMembers, array_keys($idxSet));
        if (!empty($missing)) {
            $cancelDelete = true;
            $GLOBALS['_client_alert'] =
                "그룹 대표(idx={$g}) 를 삭제하려면 같은 gidx 의 모든 행을 함께 선택해야 합니다. "
                . "누락된 idx: " . implode(', ', $missing);
            return;
        }
    }
}

/**
 * 단건 삭제 검증 — 그룹 대표(gidx=idx) 인데 같은 gidx 의 다른 행이 살아있으면 차단.
 * (자식들이 먼저 사라진 뒤에만 root 단독 삭제 허용. 동시 일괄 삭제는 위 bulkBefore 에서 통과.)
 */
function save_deleteBefore($idx, &$cancelDelete)
{
    global $__pdo;
    // bulk 컨텍스트면 통과 (위 bulkBefore 에서 이미 그룹 단위로 검증)
    if (!empty($GLOBALS['_kimgo003041_bulkCtx'])) return;

    $idx = (int)$idx;
    $st = $__pdo->prepare("SELECT idx, gidx FROM kimgo_main_list WHERE idx=? AND useflag='1'");
    $st->execute([$idx]);
    $r = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$r) return;
    // 자식 행은 자유롭게 단독 삭제 가능
    if ((int)$r['gidx'] !== (int)$r['idx']) return;

    $cnt = $__pdo->prepare("SELECT COUNT(*) FROM kimgo_main_list WHERE gidx=? AND idx<>? AND useflag='1'");
    $cnt->execute([$r['gidx'], $r['idx']]);
    $childCount = (int)$cnt->fetchColumn();
    if ($childCount > 0) {
        $cancelDelete = true;
        $GLOBALS['_client_alert'] = '파생된 내역을 먼저 삭제해야 합니다.';
    }
}

/**
 * view/modify 진입 시:
 *  ① 같은 gidx 그룹의 형제(이력) 목록을 폼 하단에 표시 (현재 idx 강조)
 *  ② "참조하여 신규입력" 버튼 추가 — 클릭 시 같은 gidx 로 새 record 작성, 현재 값 prefill
 */
function view_load(&$row)
{
    global $idx, $gubun, $__pdo;
    if (!is_array($row) || empty($row)) return;

    $gidxV = (int)($row['gidx'] ?? 0);
    if ($gidxV <= 0) return;

    // 같은 gidx 의 모든 record (최근순)
    $stmt = $__pdo->prepare(
        "SELECT table_m.idx AS idx,
                table_m.업무일자 AS 업무일자,
                IFNULL(table_w.user_name, table_m.wdater) AS 작성자,
                table_m.마감일자 AS 마감일자,
                IFNULL(table_dir_g1.폴더명,'') AS dirG1Name,
                IFNULL(table_dir_g2.폴더명,'') AS dirG2Name,
                IFNULL(table_dir_g3.폴더명,'') AS dirG3Name,
                LEFT(table_m.내용,40) AS preview,
                DATE_FORMAT(table_m.lastupdate,'%Y-%m-%d %H:%i') AS lastupdate
           FROM kimgo_main_list table_m
           LEFT JOIN mis_doc_folders table_dir_g1 ON table_dir_g1.idx=table_m.dir_g1
           LEFT JOIN mis_doc_folders table_dir_g2 ON table_dir_g2.idx=table_m.dir_g2
           LEFT JOIN mis_doc_folders table_dir_g3 ON table_dir_g3.idx=table_m.dir_g3
           LEFT JOIN mis_users table_w ON table_w.user_id=table_m.wdater
          WHERE table_m.gidx=? AND table_m.useflag='1'
          ORDER BY table_m.idx DESC"
    );
    $stmt->execute([$gidxV]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        $GLOBALS['_client_belowForm'] = [
            'type'       => 'siblingList',
            'title'      => "같은 그룹(gidx={$gidxV}) 이력 — " . count($rows) . "건 (최근순)",
            'currentIdx' => (int)$idx,
            'gubun'      => (int)$gubun,
            'columns'    => [
                ['key'=>'업무일자',  'label'=>'업무일자',  'width'=>110],
                ['key'=>'작성자',    'label'=>'작성자',    'width'=>80],
                ['key'=>'dirG1Name', 'label'=>'1차분류',   'width'=>100],
                ['key'=>'dirG2Name', 'label'=>'2차분류',   'width'=>100],
                ['key'=>'dirG3Name', 'label'=>'3차분류',   'width'=>100],
                ['key'=>'preview',   'label'=>'내용',      'width'=>240],
                ['key'=>'마감일자',  'label'=>'마감일자',  'width'=>110],
                ['key'=>'lastupdate','label'=>'수정일',    'width'=>140],
            ],
            'rows'       => $rows,
        ];
    }

    // 폼 상단 버튼 — 참조 신규입력 + 향후계획처리
    //   referInsert : 현재 record 의 값을 prefill 로 복사 → 같은 gidx 로 신규 입력
    //   forwardPlan : 동일하지만 '향후계획(hyanghugyehoek)' 의 내용을 '내용(naeyong)' 으로 옮기고,
    //                 향후계획은 공란으로 (이행 흐름)
    $GLOBALS['_client_formButtons'] = [
        ['label' => '참조하여 신규입력', 'action' => 'referInsert', 'gidx' => $gidxV],
        ['label' => '향후계획처리',     'action' => 'forwardPlan',  'gidx' => $gidxV],
    ];
}
