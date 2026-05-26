<?php
/**
 * 부품카테고리 트리 (6064) — v7 훅
 *
 * parts_cate_b (4 레벨: g4/g8/g12/g16, sort_g01~sortG04)
 *
 * 표시 규칙
 *   - autogubun 앞 4자리 같으면 g4name 한 번만, 같은 8자리면 g8name 한 번만, 같은 12자리면 g12name 한 번만 표시
 *   - g4/g8/g12/g16 항목명 좌측에 [+] 버튼 (prompt 로 받은 이름을 같은 레벨 바로 아래에 추가)
 *   - g4/g8/g12/g16 항목명 우측에 [↑] [↓] (위/아래 1칸 이동)
 *   - 상세 컬럼에 [수정] 버튼 → 6063 프로그램 idx=현재행 modify 모드 iframe 팝업
 *
 * 데이터 액션 (data-mis-action)
 *   - addCate  : { idx, value(prompt) }   → 같은 depth 로 down(+0.5) 위치에 INSERT
 *   - moveCate : { idx, direction:up|down } → sortGNN ±1.5 후 재정렬
 * 둘 다 parts_cate_b_ordering_proc() 호출로 정렬 재계산.
 */

// 트리 화면 — 헤더 클릭 정렬 비활성 (정렬하면 트리 구조 깨짐) + 선택삭제/체크박스/최근순 숨김
function pageLoad() {
    $GLOBALS['_client_disableSort'] = true;
    $GLOBALS['_client_css'] = '
        #mis-btn-bulk-delete{display:none!important}
        #mis-btn-recently{display:none!important}
        .mis-check-col{display:none!important}
    ';
}

/** 단건 삭제: view 가 아닌 base table(parts_cate_b) 에 직접 DELETE + 정렬 재계산 */
function save_deleteBefore($idx, &$cancelDelete)
{
    global $__pdo;
    $cancelDelete = true;
    try {
        $st = $__pdo->prepare('DELETE FROM parts_cate_b WHERE idx = ?');
        $st->execute([$idx]);
        $__pdo->exec('CALL parts_cate_b_ordering_proc()');
        $GLOBALS['_client_toast'] = '삭제되었습니다.';
    } catch (\Throwable $e) {
        $GLOBALS['_client_alert'] = '삭제 실패: ' . $e->getMessage();
    }
}

/** 일괄 삭제 */
function save_bulkDeleteBefore(&$idxList, &$cancelDelete)
{
    global $__pdo;
    $cancelDelete = true;
    try {
        $idxs = array_values(array_filter(array_map('intval', $idxList), fn($v) => $v > 0));
        if (empty($idxs)) { $GLOBALS['_client_toast'] = '유효한 항목이 없습니다.'; return; }
        $ph = implode(',', array_fill(0, count($idxs), '?'));
        $st = $__pdo->prepare("DELETE FROM parts_cate_b WHERE idx IN ({$ph})");
        $st->execute($idxs);
        $n = $st->rowCount();
        $__pdo->exec('CALL parts_cate_b_ordering_proc()');
        $GLOBALS['_client_toast'] = "{$n}건 삭제되었습니다.";
    } catch (\Throwable $e) {
        $GLOBALS['_client_alert'] = '삭제 실패: ' . $e->getMessage();
    }
}

function list_json_load(&$data) {
    static $prev4  = null;
    static $prev8  = null;
    static $prev12 = null;

    $ag    = (string)($data['autogubun'] ?? '');
    $cur4  = strlen($ag) >= 4  ? substr($ag, 0, 4)  : $ag;
    $cur8  = strlen($ag) >= 8  ? substr($ag, 0, 8)  : '';
    $cur12 = strlen($ag) >= 12 ? substr($ag, 0, 12) : '';

    // 각 레벨별 idx — view 의 g4num/g8num/g12num/g16num
    $g4Idx  = (int)($data['g4num']  ?? 0);
    $g8Idx  = (int)($data['g8num']  ?? 0);
    $g12Idx = (int)($data['g12num'] ?? 0);
    $g16Idx = (int)($data['g16num'] ?? 0);

    // ── g4name ──
    // ※ idx=1(root)은 불변 — 버튼 없이 이름만 표시
    if ($g4Idx === 1) {
        $data['__html']['g4name'] = htmlspecialchars((string)($data['g4name'] ?? ''), ENT_QUOTES, 'UTF-8');
    } elseif ($cur4 !== $prev4 && $g4Idx > 0) {
        $data['__html']['g4name'] = _renderTreeCell((string)($data['g4name'] ?? ''), $g4Idx);
    } else {
        $data['__html']['g4name'] = '<i></i>'; // 빈 truthy HTML — DataGrid 가 빈 문자열은 falsy 로 보고 원본 값으로 폴백하므로
    }

    // ── g8name ──
    if ($cur8 !== '' && ($cur8 !== $prev8 || $cur4 !== $prev4) && $g8Idx > 0) {
        $data['__html']['g8name'] = _renderTreeCell((string)($data['g8name'] ?? ''), $g8Idx);
    } else {
        $data['__html']['g8name'] = '<i></i>';
    }

    // ── g12name ──
    if ($cur12 !== '' && ($cur12 !== $prev12 || $cur8 !== $prev8 || $cur4 !== $prev4) && $g12Idx > 0) {
        $data['__html']['g12name'] = _renderTreeCell((string)($data['g12name'] ?? ''), $g12Idx);
    } else {
        $data['__html']['g12name'] = '<i></i>';
    }

    // ── g16name (행마다 다르므로 항상 표시) ──
    if ($g16Idx > 0 && ($data['g16name'] ?? '') !== '') {
        $data['__html']['g16name'] = _renderTreeCell((string)$data['g16name'], $g16Idx);
    } else {
        $data['__html']['g16name'] = '<i></i>';
    }

    // ── 상세 → 수정 버튼 (6063 iframe 팝업) ──
    $rowIdx = (int)($data['idx'] ?? 0);
    if ($rowIdx > 0) {
        $url = '/v7/?gubun=6063&idx=' . $rowIdx . '&ActionFlag=modify&isMenuIn=S&isPopup=Y';
        $data['__html']['virtual_fieldQninfo'] =
            '<span data-mis-iframe="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
          . ' data-mis-iframe-title="세부정보 수정 (6063)"'
          . ' class="text-link cursor-pointer underline">수정</span>';
    }

    // ── cate_idx (PK=idx) 셀 — 뷰 진입 링크 제거, 평문만 표시 ──
    if ($rowIdx > 0) {
        $data['__html']['idx'] = '<span data-mis-nolink="1" class="text-secondary">' . $rowIdx . '</span>';
    }

    $prev4  = $cur4;
    $prev8  = $cur8;
    $prev12 = $cur12;
}

/** + 추가 / ↑ / ↓ 버튼 + 항목명 HTML 생성 */
function _renderTreeCell(string $name, int $idx): string {
    $safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $btnAdd =
        '<span data-mis-action="addCate" data-mis-gubun="6064"'
      . ' data-mis-idx="' . $idx . '"'
      . ' data-mis-prompt="추가할 항목명을 입력하세요"'
      . ' class="inline-block w-4 h-4 leading-4 text-center text-link cursor-pointer rounded hover:bg-accent-dim font-bold mr-1" title="아래에 같은 레벨로 추가">+</span>';
    $btnUp =
        '<span data-mis-action="moveCate" data-mis-gubun="6064"'
      . ' data-mis-idx="' . $idx . '"'
      . " data-mis-params='" . json_encode(['direction' => 'up']) . "'"
      . ' class="inline-block w-4 h-4 leading-4 text-center text-secondary cursor-pointer rounded hover:bg-surface-2 ml-1" title="위로">↑</span>';
    $btnDown =
        '<span data-mis-action="moveCate" data-mis-gubun="6064"'
      . ' data-mis-idx="' . $idx . '"'
      . " data-mis-params='" . json_encode(['direction' => 'down']) . "'"
      . ' class="inline-block w-4 h-4 leading-4 text-center text-secondary cursor-pointer rounded hover:bg-surface-2 ml-1" title="아래로">↓</span>';
    return $btnAdd . $safe . $btnUp . $btnDown;
}

/** data-mis-action 처리 — addCate / moveCate */
function addLogic_treat(&$result) {
    global $__pdo, $misSessionUserId;
    $action = $result['action'] ?? '';

    if ($action === 'addCate') {
        $idx     = (int)($result['idx'] ?? 0);
        $newName = trim((string)($result['value'] ?? ''));
        if ($idx <= 0)            { $result['success'] = false; $result['_client_alert'] = '대상 idx 가 없습니다.'; return; }
        if ($idx === 1)           { $result['success'] = false; $result['_client_alert'] = 'root 는 변경할 수 없습니다.'; return; }
        if ($newName === '')      { $result['success'] = false; $result['_client_alert'] = '항목명을 입력하세요.'; return; }

        $st = $__pdo->prepare('SELECT depth, upidx, sort_g01, sort_g02, sort_g03, sortG04 FROM parts_cate_b WHERE idx = ? LIMIT 1');
        $st->execute([$idx]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $result['success'] = false; $result['_client_alert'] = '대상 행이 없습니다.'; return; }

        $depth = (int)$row['depth'];
        $upidx = (int)$row['upidx'];
        $s1    = (float)$row['sort_g01'];
        $s2    = (float)$row['sort_g02'];
        $s3    = (float)$row['sort_g03'];
        $s4    = (float)$row['sortG04'];

        // 클릭한 항목 바로 아래에 같은 레벨로 추가 (sort+0.5)
        if     ($depth === 1) { $s1 += 0.5; }
        elseif ($depth === 2) { $s2 += 0.5; }
        elseif ($depth === 3) { $s3 += 0.5; }
        elseif ($depth === 4) { $s4 += 0.5; }
        else { $result['success'] = false; $result['_client_alert'] = 'depth 비정상.'; return; }

        try {
            // 트리거(proc_sync_cate_tree_b) 가 매 row 마다 vv 테이블 재구축하는 것 방지 → 마지막 한 번만 sync
            $__pdo->exec('SET @skip_cate_sync = 1');
            $ins = $__pdo->prepare(
                "INSERT INTO parts_cate_b (cate_name, upidx, sort_g01, sort_g02, sort_g03, sortG04, depth, useflag, wdate, wdater)
                 VALUES (?, ?, ?, ?, ?, ?, ?, '1', NOW(), ?)"
            );
            $ins->execute([$newName, $upidx, $s1, $s2, $s3, $s4, $depth, $misSessionUserId ?? '']);
            $__pdo->exec('CALL parts_cate_b_ordering_proc()');
            $__pdo->exec('SET @skip_cate_sync = 0');
            $__pdo->exec('CALL proc_sync_cate_tree_b()');
            $result['success']      = true;
            $result['reloadList']   = true;
            $result['_client_toast'] = "[{$newName}] 추가됨";
        } catch (\Throwable $e) {
            try { $__pdo->exec('SET @skip_cate_sync = 0'); } catch (\Throwable) {}
            $result['success']      = false;
            $result['_client_alert'] = '추가 실패: ' . $e->getMessage();
        }
        return;
    }

    if ($action === 'moveCate') {
        $idx       = (int)($result['idx'] ?? 0);
        $direction = (string)($result['direction'] ?? 'up');
        if ($idx <= 0) { $result['success'] = false; $result['_client_alert'] = '대상 idx 가 없습니다.'; return; }
        if ($idx === 1) { $result['success'] = false; $result['_client_alert'] = 'root 는 변경할 수 없습니다.'; return; }
        if (!in_array($direction, ['up','down','top','bottom'], true)) {
            $direction = 'up';
        }

        $st = $__pdo->prepare('SELECT depth, upidx, sort_g01, sort_g02, sort_g03, sortG04 FROM parts_cate_b WHERE idx = ? LIMIT 1');
        $st->execute([$idx]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $result['success'] = false; $result['_client_alert'] = '대상 행이 없습니다.'; return; }

        $depth = (int)$row['depth'];
        $upidx = (int)$row['upidx'];
        $s1    = (float)$row['sort_g01'];
        $s2    = (float)$row['sort_g02'];
        $s3    = (float)$row['sort_g03'];
        $s4    = (float)$row['sortG04'];

        // sibling 그룹의 경계(맨 위/맨 아래) 검사 — up/down 일 때만, 더 이상 이동 불가능하면 안내
        if ($direction === 'up' || $direction === 'down') {
            $sortCol = $depth === 1 ? 'sort_g01'
                     : ($depth === 2 ? 'sort_g02'
                     : ($depth === 3 ? 'sort_g03' : 'sortG04'));
            $cur     = $depth === 1 ? $s1
                     : ($depth === 2 ? $s2
                     : ($depth === 3 ? $s3 : $s4));
            $sql     = "SELECT MIN({$sortCol}) AS mn, MAX({$sortCol}) AS mx
                          FROM parts_cate_b
                         WHERE useflag = 1 AND depth = {$depth}"
                     . ($depth === 1 ? '' : ' AND upidx = ' . $upidx);
            $b = $__pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
            if ($direction === 'up'   && (float)$b['mn'] >= $cur) {
                $result['success']       = false;
                $result['_client_toast'] = '이미 맨 위입니다.';
                return;
            }
            if ($direction === 'down' && (float)$b['mx'] <= $cur) {
                $result['success']       = false;
                $result['_client_toast'] = '이미 맨 아래입니다.';
                return;
            }
        }

        $delta = ($direction === 'up')   ? -1.5
              : (($direction === 'down') ?  1.5 : 0);
        $set   = null;
        if     ($direction === 'top')    { $set = 0.5; }
        elseif ($direction === 'bottom') { $set = 9999; }

        $apply = function (&$v) use ($delta, $set) {
            if ($set !== null) { $v = $set; }
            else               { $v = $v + $delta; }
        };

        if     ($depth === 1) { $apply($s1); }
        elseif ($depth === 2) { $apply($s2); }
        elseif ($depth === 3) { $apply($s3); }
        elseif ($depth === 4) { $apply($s4); }
        else { $result['success'] = false; $result['_client_alert'] = 'depth 비정상.'; return; }

        try {
            $__pdo->exec('SET @skip_cate_sync = 1');
            $up = $__pdo->prepare('UPDATE parts_cate_b SET sort_g01=?, sort_g02=?, sort_g03=?, sortG04=? WHERE idx=?');
            $up->execute([$s1, $s2, $s3, $s4, $idx]);
            $__pdo->exec('CALL parts_cate_b_ordering_proc()');
            $__pdo->exec('SET @skip_cate_sync = 0');
            $__pdo->exec('CALL proc_sync_cate_tree_b()');
            $result['success']    = true;
            $result['reloadList'] = true;
        } catch (\Throwable $e) {
            try { $__pdo->exec('SET @skip_cate_sync = 0'); } catch (\Throwable) {}
            $result['success']      = false;
            $result['_client_alert'] = '이동 실패: ' . $e->getMessage();
        }
    }
}
