<?php

/**
 * 6052 — 바코드 리다이렉트용 (숨김 메뉴)
 *
 * URL 형태:
 *   ?gubun=6052&pid={it_id}    → 6083 (부품관리) 으로 리다이렉트, it_id contains 필터
 *   ?gubun=6052&sid={창고idx}  → 6083 으로 리다이렉트, 해당 창고의 full_name contains 필터
 *
 * v6 의 re_direct() 대신 v7 은 $GLOBALS['_client_redirect'] 사용.
 */

function pageLoad() {

    global $actionFlag, $real_pid, $parent_idx, $idx;
    global $misSessionUserId, $misSessionIsAdmin, $parent_real_pid;
    global $__pdo;

    // list 호출에서만 동작 (view/save/delete 는 무시)
    if ($actionFlag !== '' && $actionFlag !== 'list') return;

    $sid = requestVB('sid');
    $pid = requestVB('pid');

    if ($sid !== '') {
        // 창고: vv_parts_storage_tree.full_name 으로 contains 필터
        $stmt = $__pdo->prepare("SELECT full_name FROM vv_parts_storage_tree WHERE idx = ? LIMIT 1");
        $stmt->execute([$sid]);
        $full_name = (string)$stmt->fetchColumn();

        if ($full_name !== '') {
            $filter = json_encode(
                [['operator' => 'contains', 'value' => $full_name, 'field' => 'table_ca_storage_idQnfull_name']],
                JSON_UNESCAPED_UNICODE
            );
            $GLOBALS['_client_redirect'] = ['gubun' => 6083, 'allFilter' => $filter];
        } else {
            $GLOBALS['_client_alert'] = "해당 창고번호: {$sid} 는 존재하지 않습니다.";
            $GLOBALS['_client_redirect'] = ['gubun' => 6083];
        }
        return;
    }

    if ($pid !== '') {
        // 상품: it_id 10 자리 zero-pad 후 직접 idx 로 view 진입 (filter 불필요)
        $pid_10 = (Len($pid) < 10) ? str_pad($pid, 10, '0', STR_PAD_LEFT) : $pid;
        $GLOBALS['_client_redirect'] = ['gubun' => 6083, 'idx' => $pid_10];
        return;
    }
}
//end pageLoad
