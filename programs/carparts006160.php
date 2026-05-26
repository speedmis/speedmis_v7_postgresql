<?php
/**
 * carparts006160 — 매뉴얼 관리 상세 (parts_cate_manual_detail)
 * 부모(carparts006159, parts_cate_manual)의 '상세정의' 탭 child.
 *
 * 1) 상세를 열었을 때 해당 master(midx)의 상세가 0건이면 자동 3건 생성 (list_json_init)
 * 2) 정렬(sort_order) 셀에 [+][△][▽][+] 버튼 (list_json_load + addLogic_treat)
 *      +  바로 위에 1건 추가 (insert_above)
 *      △  위로 이동        (move_up)
 *      ▽  아래로 이동      (move_down)
 *      +  바로 아래에 1건 추가 (insert_below)
 */

const _PCMD_GUBUN = 6160; // 이 child 메뉴 gubun (data-mis-gubun)

/**
 * 리스트 전용 — 모든 편집은 그리드 인라인 + [+][△][▽][+] 버튼으로 처리.
 *  - 수정폼(상세 패널) 진입 차단
 *  - '+등록' 버튼 숨김
 */
function pageLoad() {
    $GLOBALS['_onlyList'] = true;
}

function list_json_init() {
    global $parent_idx, $misSessionUserId;

    // child(상세) 를 master idx(midx) 로 열었을 때만 동작
    $midx = trim((string)($parent_idx ?? ''));
    if ($midx === '' || (int)$midx <= 0) return;

    // 현재 상세내역 건수 (그리드 필터와 동일: useflag='1')
    $cnt = (int) onlyOnereturnSql(
        "SELECT COUNT(*) FROM parts_cate_manual_detail WHERE useflag='1' AND midx='" . (int)$midx . "'"
    );
    if ($cnt > 0) return; // 0건일 때만 자동 생성 (중복 방지)

    $uid  = (string)($misSessionUserId ?? '');
    $base = (int) onlyOnereturnSql("SELECT COALESCE(MAX(idx),0) FROM parts_cate_manual_detail");

    for ($i = 1; $i <= 3; $i++) {
        execSql(
            "INSERT INTO parts_cate_manual_detail "
          . "(idx, midx, sort_order, useflag, flag1, flag2, wdater, lastupdater) "
          . "VALUES (?, ?, ?, '1', '', '', ?, ?)",
            [$base + $i, (int)$midx, $i, $uid, $uid]
        );
    }
}

/** 정렬 셀에 [+][△][▽][+] 버튼 주입 */
function list_json_load(&$data) {
    $idx = (int)($data['idx'] ?? 0);
    if ($idx <= 0) return;

    $val = htmlspecialchars((string)($data['sort_order'] ?? ''), ENT_QUOTES, 'UTF-8');

    $mk = function (string $action, string $sym, string $title, string $color) use ($idx) {
        return '<span data-mis-action="' . $action . '"'
             . ' data-mis-gubun="' . _PCMD_GUBUN . '"'
             . ' data-mis-idx="' . $idx . '"'
             . ' class="inline-block w-4 h-4 leading-4 text-center ' . $color
             . ' cursor-pointer rounded hover:bg-surface-2 select-none" title="' . $title . '">'
             . $sym . '</span>';
    };

    $btns = $mk('insert_above', '+', '바로 위에 1건 추가', 'text-link font-bold')
          . $mk('move_up',      '△', '위로 이동',         'text-secondary')
          . $mk('move_down',    '▽', '아래로 이동',       'text-secondary')
          . $mk('insert_below', '+', '바로 아래에 1건 추가', 'text-link font-bold');

    $data['__html']['sort_order'] =
        '<span class="inline-flex items-center gap-0.5">'
      . $btns
      . '<span class="ml-1 text-muted text-xs tabular-nums">' . $val . '</span>'
      . '</span>';
}

/** [+][△][▽][+] 액션 — insert_above / move_up / move_down / insert_below */
function addLogic_treat(&$result) {
    global $misSessionUserId;

    $action = (string)($result['action'] ?? '');
    if (!in_array($action, ['insert_above', 'insert_below', 'move_up', 'move_down'], true)) return;

    $idx = (int)($result['idx'] ?? 0);
    if ($idx <= 0) { $result['success'] = false; $result['_client_alert'] = '대상 행이 없습니다.'; return; }

    $midx = (int) onlyOnereturnSql("SELECT midx FROM parts_cate_manual_detail WHERE idx='" . $idx . "'");
    if ($midx <= 0) { $result['success'] = false; $result['_client_alert'] = '상위(midx)를 찾을 수 없습니다.'; return; }

    $uid = (string)($misSessionUserId ?? '');

    // 같은 midx 의 현재 정렬 순서대로 idx 목록
    $rows = allreturnSql("SELECT idx FROM parts_cate_manual_detail WHERE useflag='1' AND midx='" . $midx . "' ORDER BY sort_order, idx");
    $ids  = array_map(fn($r) => (int)$r['idx'], $rows);
    $pos  = array_search($idx, $ids, true);
    if ($pos === false) { $result['success'] = false; $result['_client_alert'] = '행 위치를 찾을 수 없습니다.'; return; }
    $n = count($ids);

    if ($action === 'move_up') {
        if ($pos === 0) { $result['success'] = true; $result['_client_toast'] = '이미 맨 위입니다.'; return; }
        [$ids[$pos - 1], $ids[$pos]] = [$ids[$pos], $ids[$pos - 1]];
    } elseif ($action === 'move_down') {
        if ($pos === $n - 1) { $result['success'] = true; $result['_client_toast'] = '이미 맨 아래입니다.'; return; }
        [$ids[$pos], $ids[$pos + 1]] = [$ids[$pos + 1], $ids[$pos]];
    } else { // insert_above / insert_below
        $newIdx = (int) onlyOnereturnSql("SELECT COALESCE(MAX(idx),0)+1 FROM parts_cate_manual_detail");
        $r = execSql(
            "INSERT INTO parts_cate_manual_detail "
          . "(idx, midx, sort_order, useflag, flag1, flag2, wdater, lastupdater) "
          . "VALUES (?, ?, ?, '1', '', '', ?, ?)",
            [$newIdx, $midx, 0, $uid, $uid]
        );
        if (($r['resultCode'] ?? '') !== 'success') {
            $result['success'] = false; $result['_client_alert'] = '행 추가 실패: ' . ($r['resultMessage'] ?? ''); return;
        }
        $at = ($action === 'insert_above') ? $pos : $pos + 1;
        array_splice($ids, $at, 0, [$newIdx]);
    }

    // sort_order 1..N 재부여
    foreach ($ids as $i => $rid) {
        execSql("UPDATE parts_cate_manual_detail SET sort_order=?, lastupdater=? WHERE idx=?", [$i + 1, $uid, $rid]);
    }

    // 직접 DB 변경 → 목록 캐시 무효화 후 그리드 리로드
    try { (new \App\MisCache())->invalidateByRealPid('carparts006160'); } catch (\Throwable) {}

    $result['success']       = true;
    $result['reloadList']    = true;
    $result['_client_toast'] = '처리되었습니다.';
}

/** 삭제 전: 대상 행의 midx 를 기록 (하드 DELETE 라 삭제 후엔 조회 불가) — 단건/일괄 모두 호출됨 */
function save_deleteBefore($idx, &$cancelDelete) {
    $idx = (int)$idx;
    if ($idx <= 0) return;
    $m = (int) onlyOnereturnSql("SELECT midx FROM parts_cate_manual_detail WHERE idx='" . $idx . "'");
    if ($m > 0) $GLOBALS['_pcmd_resort_midxes'][$m] = true;
}

/**
 * 삭제 후: 영향받은 midx 의 남은 행(useflag='1') 정렬값을 1,2,3,... 으로 재부여.
 * 일괄삭제(선택삭제)는 항목별로 호출되며, 마지막 호출 시점에 최종 순번이 확정됨.
 */
function save_deleteAfter($idx, &$afterScript) {
    global $misSessionUserId;
    $midxes = array_keys($GLOBALS['_pcmd_resort_midxes'] ?? []);
    $GLOBALS['_pcmd_resort_midxes'] = [];
    if (empty($midxes)) return;
    $uid = (string)($misSessionUserId ?? '');
    foreach ($midxes as $m) {
        $m = (int)$m;
        if ($m <= 0) continue;
        $rows = allreturnSql("SELECT idx FROM parts_cate_manual_detail WHERE useflag='1' AND midx='" . $m . "' ORDER BY sort_order, idx");
        $i = 1;
        foreach ($rows as $r) {
            execSql("UPDATE parts_cate_manual_detail SET sort_order=?, lastupdater=? WHERE idx=?", [$i, $uid, (int)$r['idx']]);
            $i++;
        }
    }
}
