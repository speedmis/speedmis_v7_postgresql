<?php
/**
 * carparts006162 — 앗세이 상세내역 (parts_cate_assy_detail)
 * 부모: carparts006161 앗세이 관리. child FK = midx, PK = idx(auto_increment).
 * 구분(zgubun)은 SELECT 계산식: sort_order=1 → 완제품, 그 외 → 부품N.
 *
 *  1) 목록조회 0건이면 자동 3건 생성 (list_json_init)
 *  2) 정렬(sort_order) 셀에 [+][△][▽][+] 버튼 (간편추가/이동) — sort_order 재정렬로 완제품/부품 순서 조정
 *  3) 선택삭제 후 sort_order 1..N 재부여
 *
 * ※ 테이블 엔진 InnoDB. parts_cate_assy_detail 의 NOT NULL(기본값 없음): picture/flag1/flag2/title/wdater.
 */

const _PAD_GUBUN = 6162; // 이 child 메뉴 gubun (data-mis-action 용)

/** 상단 툴바에 '주문실행' 버튼 노출 (목록 모드) */
function pageLoad() {
    global $actionFlag, $customAction;
    if ($actionFlag === 'list') {
        $GLOBALS['_client_buttons'] = [
            ['label' => '주문실행', 'action' => 'order_exec'],
        ];
        // 주문실행 요청은 캐시를 비워 cache-miss 로 처리 → 주문량 0 으로 리셋된 최신 목록을 즉시 표시
        if (($customAction ?? '') === 'order_exec') {
            try { (new \App\MisCache())->invalidateByRealPid('carparts006162'); } catch (\Throwable) {}
        }
    }
}

/** parts_cate_assy_detail 1건 INSERT (idx 자동채번) → 새 idx 반환(실패 0) */
function _pad_insert($midx, $sort, $uid) {
    $r = execSql(
        "INSERT INTO parts_cate_assy_detail "
      . "(midx, sort_order, useflag, picture, flag1, flag2, title, wdate, wdater, lastupdate, lastupdater) "
      . "VALUES (?, ?, '1', '', '', '', '', NOW(), ?, NOW(), ?)",
        [(int)$midx, (int)$sort, $uid, $uid]
    );
    return (($r['resultCode'] ?? '') === 'success') ? (int)($r['lastInsertId'] ?? 0) : 0;
}

/**
 * 상단 '주문실행' 버튼 — 주문량(assy_detail_order)>0 내역을 parts_cate_assy_order_list 에 생성.
 *  - 주문량 합계 0 → 중지 (alert)
 *  - 합계>0 → '해당 주문량으로 주문처리할까요?' 확인(1차 호출) → _confirmed 재호출 시 INSERT
 *  - midx=어미(앗세이) idx(=parent_idx), assy_detail_idx=상세 idx, order_in=주문량
 */
function _pad_order_exec() {
    global $parent_idx, $misSessionUserId, $customActionPayload;

    $midx = (int)($parent_idx ?? 0);
    if ($midx <= 0) {
        $GLOBALS['_client_alert'] = '상위(앗세이) 정보를 찾을 수 없습니다.';
        return;
    }

    // 주문량 0 초과 내역만 (useflag='1')
    $rows = allreturnSql(
        "SELECT idx, assy_detail_order FROM parts_cate_assy_detail "
      . "WHERE useflag='1' AND midx='" . $midx . "' AND assy_detail_order > 0 "
      . "ORDER BY sort_order, idx"
    );
    $sum = 0;
    foreach ($rows as $r) { $sum += (int)$r['assy_detail_order']; }

    // 주문량 합계 0 → 중지
    if ($sum <= 0) {
        $GLOBALS['_client_alert'] = '주문량이 입력된 내역이 없습니다. (주문량 합계 0)';
        return;
    }

    // 확인 — 1차 호출엔 confirm, _confirmed 면 진행
    $payload = (array)($customActionPayload ?? []);
    if (empty($payload['_confirmed'])) {
        $GLOBALS['_client_confirm'] = '해당 주문량으로 주문처리할까요?';
        return;
    }

    // parts_cate_assy_order_list INSERT (order_in=주문량)
    $uid = (string)($misSessionUserId ?? '');
    $ok  = 0;
    foreach ($rows as $r) {
        $detIdx = (int)$r['idx'];
        $qty    = (int)$r['assy_detail_order'];
        if ($detIdx <= 0 || $qty <= 0) continue;
        $res = execSql(
            "INSERT INTO parts_cate_assy_order_list "
          . "(midx, assy_detail_idx, assy_detail_order, order_in, useflag, wdate, wdater, lastupdate, lastupdater) "
          . "VALUES (?, ?, ?, ?, '1', NOW(), ?, NOW(), ?)",
            [$midx, $detIdx, $qty, $qty, $uid, $uid]
        );
        if (($res['resultCode'] ?? '') === 'success') {
            $ok++;
            // 주문 생성 완료된 상세내역의 주문량(assy_detail_order)을 0 으로 리셋
            execSql(
                "UPDATE parts_cate_assy_detail SET assy_detail_order = 0, lastupdate = NOW(), lastupdater = ? WHERE idx = ?",
                [$uid, $detIdx]
            );
        }
    }

    // 주문내역(carparts006163) 캐시 무효화 → 방금 생성한 주문이 즉시 보이도록
    try { (new \App\MisCache())->invalidateByRealPid('carparts006163'); } catch (\Throwable) {}

    $GLOBALS['_client_toast'] = $ok . '건 주문이 생성되었습니다.';
}

/** 목록조회 0건이면 자동 3건 생성 */
function list_json_init() {
    global $parent_idx, $misSessionUserId, $customAction;

    // ── 상단 '주문실행' 버튼 처리 (자동 3건 생성보다 우선) ──
    if (($customAction ?? '') === 'order_exec') {
        _pad_order_exec();
        return;
    }

    $midx = trim((string)($parent_idx ?? ''));
    if ($midx === '' || (int)$midx <= 0) return;

    $cnt = (int) onlyOnereturnSql(
        "SELECT COUNT(*) FROM parts_cate_assy_detail WHERE useflag='1' AND midx='" . (int)$midx . "'"
    );
    if ($cnt > 0) return; // 0건일 때만

    $uid = (string)($misSessionUserId ?? '');
    for ($i = 1; $i <= 3; $i++) {
        _pad_insert((int)$midx, $i, $uid);
    }
}

/** 정렬 셀에 [+][△][▽][+] 버튼 주입 (PK = idx) */
function list_json_load(&$data) {
    $idx = (int)($data['idx'] ?? 0);
    if ($idx <= 0) return;

    $val = htmlspecialchars((string)($data['sort_order'] ?? ''), ENT_QUOTES, 'UTF-8');

    $mk = function (string $action, string $sym, string $title, string $color) use ($idx) {
        return '<span data-mis-action="' . $action . '"'
             . ' data-mis-gubun="' . _PAD_GUBUN . '"'
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

    $midx = (int) onlyOnereturnSql("SELECT midx FROM parts_cate_assy_detail WHERE idx='" . $idx . "'");
    if ($midx <= 0) { $result['success'] = false; $result['_client_alert'] = '상위(midx)를 찾을 수 없습니다.'; return; }

    $uid = (string)($misSessionUserId ?? '');

    $rows = allreturnSql("SELECT idx FROM parts_cate_assy_detail WHERE useflag='1' AND midx='" . $midx . "' ORDER BY sort_order, idx");
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
        $newIdx = _pad_insert($midx, 0, $uid);
        if ($newIdx <= 0) { $result['success'] = false; $result['_client_alert'] = '행 추가 실패'; return; }
        $at = ($action === 'insert_above') ? $pos : $pos + 1;
        array_splice($ids, $at, 0, [$newIdx]);
    }

    // sort_order 1..N 재부여
    foreach ($ids as $i => $rid) {
        execSql("UPDATE parts_cate_assy_detail SET sort_order=?, lastupdater=? WHERE idx=?", [$i + 1, $uid, $rid]);
    }

    try { (new \App\MisCache())->invalidateByRealPid('carparts006162'); } catch (\Throwable) {}

    $result['success']       = true;
    $result['reloadList']    = true;
    $result['_client_toast'] = '처리되었습니다.';
}

/** 삭제 전: 대상 행의 midx 기록 */
function save_deleteBefore($idx, &$cancelDelete) {
    $idx = (int)$idx;
    if ($idx <= 0) return;
    $m = (int) onlyOnereturnSql("SELECT midx FROM parts_cate_assy_detail WHERE idx='" . $idx . "'");
    if ($m > 0) $GLOBALS['_pad_resort_midxes'][$m] = true;
}

/** 삭제 후: 영향받은 midx 의 남은 행(useflag='1') sort_order 1..N 재부여 */
function save_deleteAfter($idx, &$afterScript) {
    global $misSessionUserId;
    $midxes = array_keys($GLOBALS['_pad_resort_midxes'] ?? []);
    $GLOBALS['_pad_resort_midxes'] = [];
    if (empty($midxes)) return;
    $uid = (string)($misSessionUserId ?? '');
    foreach ($midxes as $m) {
        $m = (int)$m;
        if ($m <= 0) continue;
        $rows = allreturnSql("SELECT idx FROM parts_cate_assy_detail WHERE useflag='1' AND midx='" . $m . "' ORDER BY sort_order, idx");
        $i = 1;
        foreach ($rows as $r) {
            execSql("UPDATE parts_cate_assy_detail SET sort_order=?, lastupdater=? WHERE idx=?", [$i, $uid, (int)$r['idx']]);
            $i++;
        }
    }
}
