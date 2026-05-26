<?php
/**
 * 창고 트리 (6040) — v7 훅
 *
 * parts_storage (10 레벨: g4/g8/g12/g16/g20/g24/g28/g32/g36/g40, sortG01~sortG10)
 *
 * 표시 규칙
 *   - 같은 4n 자리 prefix 인 행에서는 해당 레벨 이름을 한 번만 표시
 *   - 각 레벨 항목명 좌측에 [+] 버튼 (prompt 로 받은 이름을 같은 레벨 바로 아래에 추가)
 *   - 각 레벨 항목명 우측에 [↑] [↓] (위/아래 1칸 이동)
 *   - 상세 컬럼에 [수정] 버튼 → 6039 프로그램 idx=현재행 modify 모드 iframe 팝업
 *
 * 데이터 액션 (data-mis-action)
 *   - addCate  : { idx, value(prompt) }   → 같은 depth 로 down(+0.5) 위치에 INSERT
 *   - moveCate : { idx, direction:up|down } → sortGNN ±1.5 후 재정렬
 * 둘 다 parts_storage_ordering_proc() 호출로 정렬 재계산.
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

/** 단건 삭제: view 가 아닌 base table(parts_storage) 에 직접 DELETE + 정렬 재계산 */
function save_deleteBefore($idx, &$cancelDelete)
{
    global $__pdo;
    $cancelDelete = true;
    try {
        $st = $__pdo->prepare('DELETE FROM parts_storage WHERE idx = ?');
        $st->execute([$idx]);
        $__pdo->exec('CALL parts_storage_ordering_proc()');
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
        $st = $__pdo->prepare("DELETE FROM parts_storage WHERE idx IN ({$ph})");
        $st->execute($idxs);
        $n = $st->rowCount();
        $__pdo->exec('CALL parts_storage_ordering_proc()');
        $GLOBALS['_client_toast'] = "{$n}건 삭제되었습니다.";
    } catch (\Throwable $e) {
        $GLOBALS['_client_alert'] = '삭제 실패: ' . $e->getMessage();
    }
}

/**
 * v6 misMenuList_change → v7 before_query 포팅.
 * 본인 거점(g4name) 의 창고만 보이도록 base_filter 동적 설정.
 * admin/gadmin 은 '포천' 으로 매핑 (DB 의 최상위 거점명).
 */
function before_query(&$menu, &$fields, &$params) {
    $uid = (string)($GLOBALS['misSessionUserId'] ?? '');
    if ($uid === 'admin' || $uid === 'gadmin') $uid = '포천';
    // 한글 포함 가능 — 작은따옴표/세미콜론/백슬래시만 차단해 SQL 인젝션 방지
    $safe = preg_replace("/['\";\\\\]/", '', $uid);
    if ($safe === '') return;
    $menu['base_filter'] = "table_m.g4name = '" . $safe . "'";
}

const _STORAGE_LEVELS = [4, 8, 12, 16, 20, 24, 28, 32, 36, 40];

function list_json_load(&$data) {
    static $prev = [4 => null, 8 => null, 12 => null, 16 => null, 20 => null,
                    24 => null, 28 => null, 32 => null, 36 => null];

    $ag = (string)($data['autogubun'] ?? '');

    foreach (_STORAGE_LEVELS as $i => $charLen) {       // 4, 8, 12, ..., 40
        $depth      = $i + 1;                            // 1..10
        $aliasName  = "g{$charLen}name";
        $idxField   = "g{$charLen}num";
        $idxVal     = (int)($data[$idxField] ?? 0);
        $curPrefix  = strlen($ag) >= $charLen ? substr($ag, 0, $charLen) : '';

        // root (idx=1) 는 버튼 없이 이름만
        if ($depth === 1 && $idxVal === 1) {
            $data['__html'][$aliasName] = htmlspecialchars((string)($data[$aliasName] ?? ''), ENT_QUOTES, 'UTF-8');
            $prev[$charLen] = $curPrefix;
            continue;
        }

        // 표시 조건: 마지막 레벨이면 항상, 그 외에는 prefix 변화 시
        $isLastLevel = ($charLen === 40);
        $changed     = false;
        if ($isLastLevel) {
            $changed = $idxVal > 0 && ($data[$aliasName] ?? '') !== '';
        } else {
            // 현재 prefix 가 비어있지 않고, 자신 또는 상위 prefix 가 변경됨
            if ($curPrefix !== '' && $idxVal > 0) {
                $changed = ($prev[$charLen] !== $curPrefix);
                if (!$changed) {
                    foreach (_STORAGE_LEVELS as $cl) {
                        if ($cl >= $charLen) break;
                        $upPrefix = strlen($ag) >= $cl ? substr($ag, 0, $cl) : '';
                        if ($prev[$cl] !== $upPrefix) { $changed = true; break; }
                    }
                }
            }
        }

        if ($changed) {
            $data['__html'][$aliasName] = _renderStorageTreeCell((string)($data[$aliasName] ?? ''), $idxVal);
        } else {
            $data['__html'][$aliasName] = '<i></i>';
        }

        if (!$isLastLevel) {
            $prev[$charLen] = $curPrefix;
        }
    }

    // ── 상세 → 수정 버튼 (6039 iframe 팝업) ──
    $rowIdx = (int)($data['idx'] ?? 0);
    if ($rowIdx > 0) {
        $url = '/v7/?gubun=6039&idx=' . $rowIdx . '&ActionFlag=modify&isMenuIn=S&isPopup=Y';
        $data['__html']['virtual_fieldQninfo'] =
            '<span data-mis-iframe="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
          . ' data-mis-iframe-title="창고 정보 수정 (6039)"'
          . ' class="text-link cursor-pointer underline">수정</span>';

        // 평문 idx 표시
        $data['__html']['idx'] = '<span data-mis-nolink="1" class="text-secondary">' . $rowIdx . '</span>';
    }
}

function _renderStorageTreeCell(string $name, int $idx): string {
    $safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $btnAdd =
        '<span data-mis-action="addCate" data-mis-gubun="6040"'
      . ' data-mis-idx="' . $idx . '"'
      . ' data-mis-prompt="추가할 항목명을 입력하세요"'
      . ' class="inline-block w-4 h-4 leading-4 text-center text-link cursor-pointer rounded hover:bg-accent-dim font-bold mr-1" title="아래에 같은 레벨로 추가">+</span>';
    $btnUp =
        '<span data-mis-action="moveCate" data-mis-gubun="6040"'
      . ' data-mis-idx="' . $idx . '"'
      . " data-mis-params='" . json_encode(['direction' => 'up']) . "'"
      . ' class="inline-block w-4 h-4 leading-4 text-center text-secondary cursor-pointer rounded hover:bg-surface-2 ml-1" title="위로">↑</span>';
    $btnDown =
        '<span data-mis-action="moveCate" data-mis-gubun="6040"'
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

        $st = $__pdo->prepare(
            'SELECT depth, upidx, sortG01, sortG02, sortG03, sortG04, sortG05,
                                sortG06, sortG07, sortG08, sortG09, sortG10
               FROM parts_storage WHERE idx = ? LIMIT 1'
        );
        $st->execute([$idx]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $result['success'] = false; $result['_client_alert'] = '대상 행이 없습니다.'; return; }

        $depth = (int)$row['depth'];
        if ($depth < 1 || $depth > 10) {
            $result['success'] = false; $result['_client_alert'] = 'depth 비정상.'; return;
        }
        $upidx = (int)$row['upidx'];
        $sorts = [];
        for ($i = 1; $i <= 10; $i++) {
            $col = 'sortG' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $sorts[$i] = (float)$row[$col];
        }

        // 같은 depth 바로 아래 (sort+0.5)
        $sorts[$depth] += 0.5;

        try {
            $cols = ['storage_name', 'upidx'];
            $vals = [$newName, $upidx];
            for ($i = 1; $i <= 10; $i++) {
                $cols[] = 'sortG' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                $vals[] = $sorts[$i];
            }
            $cols[] = 'depth';   $vals[] = $depth;
            $cols[] = 'useflag'; $vals[] = '1';
            $cols[] = 'wdate';   $vals[] = date('Y-m-d H:i:s');
            $cols[] = 'wdater';  $vals[] = $misSessionUserId ?? '';

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $colsSql      = implode(',', $cols);
            $ins = $__pdo->prepare("INSERT INTO parts_storage ({$colsSql}) VALUES ({$placeholders})");
            $ins->execute($vals);
            $__pdo->exec('CALL parts_storage_ordering_proc()');
            $result['success']       = true;
            $result['reloadList']    = true;
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
        if ($idx <= 0)  { $result['success'] = false; $result['_client_alert'] = '대상 idx 가 없습니다.'; return; }
        if ($idx === 1) { $result['success'] = false; $result['_client_alert'] = 'root 는 변경할 수 없습니다.'; return; }
        if (!in_array($direction, ['up','down','top','bottom'], true)) {
            $direction = 'up';
        }

        $st = $__pdo->prepare(
            'SELECT depth, upidx, sortG01, sortG02, sortG03, sortG04, sortG05,
                                sortG06, sortG07, sortG08, sortG09, sortG10
               FROM parts_storage WHERE idx = ? LIMIT 1'
        );
        $st->execute([$idx]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $result['success'] = false; $result['_client_alert'] = '대상 행이 없습니다.'; return; }

        $depth = (int)$row['depth'];
        if ($depth < 1 || $depth > 10) {
            $result['success'] = false; $result['_client_alert'] = 'depth 비정상.'; return;
        }
        $upidx = (int)$row['upidx'];
        $sorts = [];
        for ($i = 1; $i <= 10; $i++) {
            $col = 'sortG' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $sorts[$i] = (float)$row[$col];
        }

        // 경계 검사 제거 — sibling 그룹 내 맨 위/아래여도 사용자가 클릭 가능하게 허용.
        // ±1.5 적용 후 ordering_proc 가 재정렬하므로, 경계 행이면 결과적으로 위치 변화 없음.

        if     ($direction === 'top')    { $sorts[$depth] = 0.5; }
        elseif ($direction === 'bottom') { $sorts[$depth] = 9999; }
        elseif ($direction === 'up')     { $sorts[$depth] -= 1.5; }
        else                             { $sorts[$depth] += 1.5; }

        try {
            $setSql = [];
            $vals   = [];
            for ($i = 1; $i <= 10; $i++) {
                $col = 'sortG' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                $setSql[] = "{$col}=?";
                $vals[]   = $sorts[$i];
            }
            $vals[] = $idx;
            $up = $__pdo->prepare(
                'UPDATE parts_storage SET ' . implode(',', $setSql) . ' WHERE idx=?'
            );
            $up->execute($vals);
            $__pdo->exec('CALL parts_storage_ordering_proc()');
            $result['success']    = true;
            $result['reloadList'] = true;
        } catch (\Throwable $e) {
            $result['success']      = false;
            $result['_client_alert'] = '이동 실패: ' . $e->getMessage();
        }
    }
}
