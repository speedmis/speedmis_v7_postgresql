<?php
/**
 * 문서폴더 트리 (6166) — 기준 6038 대응 (mis_doc_folders + 폴더명)
 *  - 트리 셀 표시 + [+] [↑] [↓] 버튼
 *  - 상세 → [수정] 버튼 → 6165 (문서폴더 평면) iframe 팝업
 */

function pageLoad()
{
    $GLOBALS['_client_disableSort'] = true;
    $GLOBALS['_client_css'] = '
        #mis-btn-bulk-delete{display:none!important}
        #mis-btn-recently{display:none!important}
        .mis-check-col{display:none!important}
    ';
}

/** 단건 삭제: view 가 아닌 base table(mis_doc_folders) 에 직접 DELETE + 정렬 재계산 */
function save_deleteBefore($idx, &$cancelDelete)
{
    global $__pdo;
    $cancelDelete = true;  // framework 의 view DELETE 차단
    try {
        $st = $__pdo->prepare('DELETE FROM mis_doc_folders WHERE idx = ?');
        $st->execute([$idx]);
        $__pdo->exec('CALL mis_doc_folders_ordering_proc()');
        $GLOBALS['_client_toast'] = '삭제되었습니다.';
    } catch (\Throwable $e) {
        $GLOBALS['_client_alert'] = '삭제 실패: ' . $e->getMessage();
    }
}

/** 일괄 삭제: idxList 한 번에 DELETE + 정렬 재계산 */
function save_bulkDeleteBefore(&$idxList, &$cancelDelete)
{
    global $__pdo;
    $cancelDelete = true;  // framework 의 view DELETE 차단 (toast 설정 시 success=true 로 처리됨)
    try {
        $idxs = array_values(array_filter(array_map('intval', $idxList), fn($v) => $v > 0));
        if (empty($idxs)) { $GLOBALS['_client_toast'] = '유효한 항목이 없습니다.'; return; }
        $ph = implode(',', array_fill(0, count($idxs), '?'));
        $st = $__pdo->prepare("DELETE FROM mis_doc_folders WHERE idx IN ({$ph})");
        $st->execute($idxs);
        $n = $st->rowCount();
        $__pdo->exec('CALL mis_doc_folders_ordering_proc()');
        $GLOBALS['_client_toast'] = "{$n}건 삭제되었습니다.";
    } catch (\Throwable $e) {
        $GLOBALS['_client_alert'] = '삭제 실패: ' . $e->getMessage();
    }
}

function list_json_load(&$data)
{
    static $prev4 = null;
    static $prev8 = null;
    static $prev12 = null;
    static $prev16 = null;

    $ag    = (string)($data['autogubun'] ?? '');
    $cur4  = strlen($ag) >=  4 ? substr($ag, 0,  4) : $ag;
    $cur8  = strlen($ag) >=  8 ? substr($ag, 0,  8) : '';
    $cur12 = strlen($ag) >= 12 ? substr($ag, 0, 12) : '';
    $cur16 = strlen($ag) >= 16 ? substr($ag, 0, 16) : '';

    $g4Idx  = (int)($data['g4num']  ?? 0);
    $g8Idx  = (int)($data['g8num']  ?? 0);
    $g12Idx = (int)($data['g12num'] ?? 0);
    $g16Idx = (int)($data['g16num'] ?? 0);
    $g20Idx = (int)($data['g20num'] ?? 0);

    if ($g4Idx === 1 || strpos((string)($data['g4name'] ?? ''), 'ROOT') !== false) {
        $data['__html']['g4name'] = htmlspecialchars((string)($data['g4name'] ?? ''), ENT_QUOTES, 'UTF-8');
    } elseif ($cur4 !== $prev4 && $g4Idx > 0) {
        $data['__html']['g4name'] = _renderTreeCell166((string)($data['g4name'] ?? ''), $g4Idx);
    } else {
        $data['__html']['g4name'] = '<i></i>';
    }

    if ($cur8 !== '' && ($cur8 !== $prev8 || $cur4 !== $prev4) && $g8Idx > 0) {
        $data['__html']['g8name'] = _renderTreeCell166((string)($data['g8name'] ?? ''), $g8Idx);
    } else {
        $data['__html']['g8name'] = '<i></i>';
    }

    if ($cur12 !== '' && ($cur12 !== $prev12 || $cur8 !== $prev8) && $g12Idx > 0) {
        $data['__html']['g12name'] = _renderTreeCell166((string)$data['g12name'], $g12Idx);
    } else {
        $data['__html']['g12name'] = '<i></i>';
    }

    if (array_key_exists('g16name', $data)) {
        if ($cur16 !== '' && ($cur16 !== $prev16 || $cur12 !== $prev12) && $g16Idx > 0) {
            $data['__html']['g16name'] = _renderTreeCell166((string)$data['g16name'], $g16Idx);
        } else {
            $data['__html']['g16name'] = '<i></i>';
        }
    }
    if (array_key_exists('g20name', $data)) {
        if ($g20Idx > 0 && ($data['g20name'] ?? '') !== '') {
            $data['__html']['g20name'] = _renderTreeCell166((string)$data['g20name'], $g20Idx);
        } else {
            $data['__html']['g20name'] = '<i></i>';
        }
    }

    $rowIdx = (int)($data['idx'] ?? 0);
    if ($rowIdx > 0) {
        $url = '/v7/?gubun=6165&idx=' . $rowIdx . '&ActionFlag=modify&isMenuIn=S&isPopup=Y';
        $data['__html']['virtual_fieldQninfo'] =
            '<span data-mis-iframe="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
          . ' data-mis-iframe-title="문서폴더 수정 (6165)"'
          . ' class="text-link cursor-pointer underline">수정</span>';

        $data['__html']['idx'] = '<span data-mis-nolink="1" class="text-secondary">' . $rowIdx . '</span>';
    }

    // ── 자식 추가 [+] '여기에 추가하기' (5단계) ──
    $agLen  = strlen((string)($data['autogubun'] ?? ''));
    $isRoot = (($data['g4name'] ?? '') === 'ROOT' || $g4Idx === 1);
    if ($rowIdx > 0 && !$isRoot && in_array($agLen, [4, 8, 12, 16], true)) {
        $childCell = ['4'=>'g8name','8'=>'g12name','12'=>'g16name','16'=>'g20name'][(string)$agLen];
        $btn = _renderAddChildBtn166($rowIdx);
        $existing = $data['__html'][$childCell] ?? '<i></i>';
        $data['__html'][$childCell] = ($existing === '<i></i>') ? $btn : ($existing . ' ' . $btn);
    }

    $prev4  = $cur4;
    $prev8  = $cur8;
    $prev12 = $cur12;
    $prev16 = $cur16;
}

/** '여기에 추가하기' [+] — 우측칸 자식 INSERT (mis_doc_folders) */
function _renderAddChildBtn166(int $parentIdx): string
{
    return '<span data-mis-action="addChildCate" data-mis-gubun="6166"'
         . ' data-mis-idx="' . $parentIdx . '"'
         . ' data-mis-prompt="여기에 추가할 항목명을 입력하세요"'
         . ' class="inline-block px-1 leading-4 text-center text-link cursor-pointer rounded hover:bg-accent-dim font-bold ml-1" title="여기에 자식으로 추가">＋</span>';
}

function _renderTreeCell166(string $name, int $idx): string
{
    $safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $btnAdd =
        '<span data-mis-action="addCate" data-mis-gubun="6166"'
      . ' data-mis-idx="' . $idx . '"'
      . ' data-mis-prompt="추가할 항목명을 입력하세요"'
      . ' class="inline-block w-4 h-4 leading-4 text-center text-link cursor-pointer rounded hover:bg-accent-dim font-bold mr-1" title="아래에 같은 레벨로 추가">+</span>';
    $btnUp =
        '<span data-mis-action="moveCate" data-mis-gubun="6166"'
      . ' data-mis-idx="' . $idx . '"'
      . " data-mis-params='" . json_encode(['direction' => 'up']) . "'"
      . ' class="inline-block w-4 h-4 leading-4 text-center text-secondary cursor-pointer rounded hover:bg-surface-2 ml-1" title="위로">↑</span>';
    $btnDown =
        '<span data-mis-action="moveCate" data-mis-gubun="6166"'
      . ' data-mis-idx="' . $idx . '"'
      . " data-mis-params='" . json_encode(['direction' => 'down']) . "'"
      . ' class="inline-block w-4 h-4 leading-4 text-center text-secondary cursor-pointer rounded hover:bg-surface-2 ml-1" title="아래로">↓</span>';
    return $btnAdd . $safe . $btnUp . $btnDown;
}

/** data-mis-action 처리 — addCate / addChildCate / moveCate (mis_doc_folders + 폴더명) */
function addLogic_treat(&$result)
{
    global $__pdo, $misSessionUserId;
    $action = $result['action'] ?? '';

    // ─── 자식 추가 ('여기에 추가하기' [+] 버튼) ───
    if ($action === 'addChildCate') {
        $parentIdx = (int)($result['idx'] ?? 0);
        $newName   = trim((string)($result['value'] ?? ''));
        if ($parentIdx <= 0) { $result['success'] = false; $result['_client_alert'] = '대상 idx 가 없습니다.'; return; }
        if ($newName === '') { $result['success'] = false; $result['_client_alert'] = '항목명을 입력하세요.'; return; }

        $st = $__pdo->prepare('SELECT depth, autogubun, sort_g2, sort_g4, sort_g6, sort_g8, sort_g10 FROM mis_doc_folders WHERE idx = ? LIMIT 1');
        $st->execute([$parentIdx]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $result['success'] = false; $result['_client_alert'] = '대상 행이 없습니다.'; return; }

        $pAg    = (string)($row['autogubun'] ?? '');
        $pAgLen = strlen($pAg);
        $pDepth = (int)$row['depth'];
        if ($pDepth <= 0) $pDepth = max(1, intdiv($pAgLen, 4));
        $cDepth = $pDepth + 1;
        if ($cDepth > 5) { $result['success'] = false; $result['_client_alert'] = '5단계까지만 지원합니다.'; return; }

        $s2  = (float)$row['sort_g2'];
        $s4  = (float)$row['sort_g4'];
        $s6  = (float)$row['sort_g6'];
        $s8  = (float)$row['sort_g8'];
        $s10 = (float)$row['sort_g10'];
        // '여기에 추가하기' = 같은 depth 의 첫번째 위치 → sort 값 0.5 (procedure ROW_NUMBER 후 1번)
        if     ($cDepth === 1) { $s2 = 0.5; $s4 = 0; $s6 = 0; $s8 = 0; $s10 = 0; }
        elseif ($cDepth === 2) { $s4 = 0.5; $s6 = 0; $s8 = 0; $s10 = 0; }
        elseif ($cDepth === 3) { $s6 = 0.5; $s8 = 0; $s10 = 0; }
        elseif ($cDepth === 4) { $s8 = 0.5; $s10 = 0; }
        elseif ($cDepth === 5) { $s10 = 0.5; }

        // 임시 autogubun: 부모 autogubun + '0000' (sort 정렬 시 다른 형제보다 앞으로 위치) — procedure 가 정수로 재구성
        $newAg = ($cDepth === 1) ? '0000' : ($pAg . '0000');

        try {
            $ins = $__pdo->prepare(
                "INSERT INTO mis_doc_folders (`폴더명`, upidx, autogubun, sort_g2, sort_g4, sort_g6, sort_g8, sort_g10, depth, useflag, wdate, wdater)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '1', NOW(), ?)"
            );
            $ins->execute([$newName, $parentIdx, $newAg, $s2, $s4, $s6, $s8, $s10, $cDepth, $misSessionUserId ?? '']);
            $__pdo->exec('CALL mis_doc_folders_ordering_proc()');
            $result['success']      = true;
            $result['reloadList']   = true;
            $result['_client_toast'] = "[{$newName}] 자식으로 추가됨";
        } catch (\Throwable $e) {
            $result['success']      = false;
            $result['_client_alert'] = '추가 실패: ' . $e->getMessage();
        }
        return;
    }

    if ($action === 'addCate') {
        $idx     = (int)($result['idx'] ?? 0);
        $newName = trim((string)($result['value'] ?? ''));
        if ($idx <= 0)       { $result['success'] = false; $result['_client_alert'] = '대상 idx 가 없습니다.'; return; }
        if ($newName === '') { $result['success'] = false; $result['_client_alert'] = '항목명을 입력하세요.'; return; }

        $st = $__pdo->prepare('SELECT depth, upidx, sort_g2, sort_g4, sort_g6 FROM mis_doc_folders WHERE idx = ? LIMIT 1');
        $st->execute([$idx]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $result['success'] = false; $result['_client_alert'] = '대상 행이 없습니다.'; return; }
        if ((int)$row['depth'] === 0) { $result['success'] = false; $result['_client_alert'] = 'ROOT 는 변경할 수 없습니다.'; return; }

        $depth = (int)$row['depth'];
        $upidx = (int)$row['upidx'];
        $s2    = (float)$row['sort_g2'];
        $s4    = (float)$row['sort_g4'];
        $s6    = (float)$row['sort_g6'];

        if     ($depth === 1) { $s2 += 0.5; }
        elseif ($depth === 2) { $s4 += 0.5; }
        elseif ($depth === 3) { $s6 += 0.5; }
        else { $result['success'] = false; $result['_client_alert'] = 'depth 비정상.'; return; }

        try {
            $ins = $__pdo->prepare(
                "INSERT INTO mis_doc_folders (`폴더명`, upidx, sort_g2, sort_g4, sort_g6, sort_g8, sort_g10, depth, useflag, wdate, wdater)
                 VALUES (?, ?, ?, ?, ?, 0, 0, ?, '1', NOW(), ?)"
            );
            $ins->execute([$newName, $upidx, $s2, $s4, $s6, $depth, $misSessionUserId ?? '']);
            $__pdo->exec('CALL mis_doc_folders_ordering_proc()');
            $result['success']      = true;
            $result['reloadList']   = true;
            $result['_client_toast'] = "[{$newName}] 추가됨";
        } catch (\Throwable $e) {
            $result['success']      = false;
            $result['_client_alert'] = '추가 실패: ' . $e->getMessage();
        }
        return;
    }

    if ($action === 'moveCate') {
        $idx       = (int)($result['idx'] ?? 0);
        $direction = (string)($result['direction'] ?? 'up');
        if ($idx <= 0) { $result['success'] = false; $result['_client_alert'] = '대상 idx 가 없습니다.'; return; }
        if (!in_array($direction, ['up','down','top','bottom'], true)) $direction = 'up';

        $st = $__pdo->prepare('SELECT depth, upidx, sort_g2, sort_g4, sort_g6 FROM mis_doc_folders WHERE idx = ? LIMIT 1');
        $st->execute([$idx]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $result['success'] = false; $result['_client_alert'] = '대상 행이 없습니다.'; return; }
        if ((int)$row['depth'] === 0) { $result['success'] = false; $result['_client_alert'] = 'ROOT 는 변경할 수 없습니다.'; return; }

        $depth = (int)$row['depth'];
        $upidx = (int)$row['upidx'];
        $s2    = (float)$row['sort_g2'];
        $s4    = (float)$row['sort_g4'];
        $s6    = (float)$row['sort_g6'];

        if ($direction === 'up' || $direction === 'down') {
            $sortCol = $depth === 1 ? 'sort_g2' : ($depth === 2 ? 'sort_g4' : 'sort_g6');
            $cur     = $depth === 1 ? $s2 : ($depth === 2 ? $s4 : $s6);
            $sql     = "SELECT MIN({$sortCol}) AS mn, MAX({$sortCol}) AS mx
                          FROM mis_doc_folders
                         WHERE useflag = '1' AND depth = {$depth}"
                     . ($depth === 1 ? '' : ' AND upidx = ' . $upidx);
            $b = $__pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
            if ($direction === 'up'   && (float)$b['mn'] >= $cur) { $result['success'] = false; $result['_client_toast'] = '이미 맨 위입니다.';   return; }
            if ($direction === 'down' && (float)$b['mx'] <= $cur) { $result['success'] = false; $result['_client_toast'] = '이미 맨 아래입니다.'; return; }
        }

        $delta = ($direction === 'up') ? -1.5 : (($direction === 'down') ? 1.5 : 0);
        $set   = ($direction === 'top') ? 0.5 : (($direction === 'bottom') ? 9999 : null);

        $apply = function (&$v) use ($delta, $set) { if ($set !== null) $v = $set; else $v += $delta; };
        if     ($depth === 1) { $apply($s2); }
        elseif ($depth === 2) { $apply($s4); }
        elseif ($depth === 3) { $apply($s6); }
        else { $result['success'] = false; $result['_client_alert'] = 'depth 비정상.'; return; }

        try {
            $up = $__pdo->prepare('UPDATE mis_doc_folders SET sort_g2=?, sort_g4=?, sort_g6=? WHERE idx=?');
            $up->execute([$s2, $s4, $s6, $idx]);
            $__pdo->exec('CALL mis_doc_folders_ordering_proc()');
            $result['success']    = true;
            $result['reloadList'] = true;
        } catch (\Throwable $e) {
            $result['success']      = false;
            $result['_client_alert'] = '이동 실패: ' . $e->getMessage();
        }
    }
}
